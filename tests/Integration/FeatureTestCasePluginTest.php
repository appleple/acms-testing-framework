<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration;

use Acms\Services\Common\InjectTemplate;
use Acms\Services\Facades\Application;
use Acms\Services\Http\Kernel\Extension\StageMiddlewareRegistry;
use Acms\TestingFramework\FeatureTestCase;
use Acms\TestingFramework\Helpers\PluginActivator;
use Acms\TestingFramework\Internal\RequestRegistrations;
use Acms\TestingFramework\Tests\Fixtures\FeaturePlugin\Hook;
use Acms\TestingFramework\Tests\Fixtures\FeaturePlugin\InitHook;
use Acms\TestingFramework\Tests\Fixtures\FeaturePlugin\MarkerMarkdownConverter;
use Acms\TestingFramework\Tests\Fixtures\FeaturePlugin\MarkerMiddleware;
use Acms\TestingFramework\Tests\Fixtures\FeaturePlugin\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * プラグインを有効化して `get()` / `post()` を通すと、プラグインが `earlyInit()` で登録したミドルウェアと、`init()` で登録した Hook が、
 * 本番と同じように動くことを確かめる（プラグインの開発者が、自分のプラグインを機能テストで確かめる使い方）
 */
#[CoversClass(FeatureTestCase::class)]
final class FeatureTestCasePluginTest extends FeatureTestCase
{
    private const HOST = 'plugin.example.test';

    private int $blogId = 0;

    protected function setUpSite(): void
    {
        $this->blogId = $this->seedBlog(self::HOST, 'Plugin Blog');
        $this->withHost(self::HOST);
    }

    #[Test]
    #[TestDox('有効化していないプラグインのミドルウェアと Hook は動かない')]
    public function 有効化していなければ動かない(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $this->assertSame('', $response->header('X-Feature-Plugin'));
        $response->assertDontSee('feature-plugin-hook');
    }

    #[Test]
    #[TestDox('有効化したプラグインが earlyInit() で登録したミドルウェアと、init() で登録した Hook が動く')]
    public function 有効化したプラグインのミドルウェアとHookが動く(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);

        $response = $this->get('/');

        $response->assertStatus(200)->assertHeader('X-Feature-Plugin', 'middleware')->assertSee('feature-plugin-hook');
    }

    #[Test]
    #[TestDox('リクエストをまたいで登録が積み上がらない（1 リクエストごとに新しい登録先）')]
    public function リクエストごとに新しい登録先(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);

        MarkerMiddleware::$calls = 0;

        $this->get('/')->assertHeader('X-Feature-Plugin', 'middleware');
        $this->assertSame(1, MarkerMiddleware::$calls);
        $second = $this->get('/');

        // 登録が積み上がると、2 回目は 3 回になる
        $this->assertSame(2, MarkerMiddleware::$calls);
        $this->assertSame(1, substr_count($second->body(), 'feature-plugin-hook'));
    }

    #[Test]
    #[TestDox('無効化すると、次のリクエストからプラグインは動かない')]
    public function 無効化すると動かない(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        $this->get('/')->assertHeader('X-Feature-Plugin');

        PluginActivator::deactivate(ServiceProvider::class, $this->blogId);

        $this->assertSame('', $this->get('/')->header('X-Feature-Plugin'));
    }

    #[Test]
    #[TestDox('無効化した直後のリクエストに、前のリクエストのプラグインの Hook が残って発火しない')]
    public function 無効化した後のリクエストにHookが残らない(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        $this->get('/')->assertSee('feature-plugin-hook');

        PluginActivator::deactivate(ServiceProvider::class, $this->blogId);

        $this->get('/')->assertDontSee('feature-plugin-hook');
    }

    #[Test]
    #[TestDox('プラグインを有効化していない別のブログへのリクエストに、有効化したブログの Hook が残って発火しない')]
    public function 別ブログのリクエストにHookが残らない(): void
    {
        $otherHost = 'plain.example.test';
        $this->seedBlog($otherHost, 'Plain Blog');
        PluginActivator::activate(ServiceProvider::class, $this->blogId);

        $this->get('/')->assertSee('feature-plugin-hook');
        $other = $this->withHost($otherHost)->get('/');

        $other->assertStatus(200);
        $other->assertDontSee('feature-plugin-hook');
        $this->assertSame('', $other->header('X-Feature-Plugin'));
    }

    #[Test]
    #[TestDox('テストが差し替えたコンテナの http.middleware は、tearDown で元の登録先へ戻る')]
    public function 登録先はtearDownで戻る(): void
    {
        $original = Application::make('http.middleware');
        $this->get('/');

        $this->assertNotSame($original, Application::make('http.middleware'));
        $this->assertInstanceOf(StageMiddlewareRegistry::class, Application::make('http.middleware'));
        $this->tearDown();

        $this->assertSame($original, Application::make('http.middleware'));
    }

    #[Test]
    #[TestDox('プラグインの init() が登録した Hook は、リクエストの後に取り除かれ、後のテストへ残らない')]
    public function Hookの登録はリクエストの後に戻る(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        $this->get('/')->assertSee('feature-plugin-hook');

        $this->assertFalse(\ACMS_Hook::singleton()->getHook('FeatureFixturePlugin'));
        $this->tearDown();
        $this->assertFalse(\ACMS_Hook::singleton()->getHook('FeatureFixturePlugin'));
    }

    #[Test]
    #[TestDox('テストが get() の前に自分で attach した Hook は、リクエストをまたいで残り、2 回目の get() でも発火する')]
    public function テストが足したHookはリクエストをまたいで残る(): void
    {
        $hook = new class {
            public int $fired = 0;

            /**
             * @param string $contents
             */
            public function afterBuild(&$contents): void
            {
                $this->fired++;
                $contents .= '<!-- test-owned-hook -->';
            }
        };
        \ACMS_Hook::singleton()->attach('TestOwnedHook', $hook);

        $this->get('/')->assertSee('test-owned-hook');
        $this->assertSame($hook, \ACMS_Hook::singleton()->getHook('TestOwnedHook'));
        $this->get('/')->assertSee('test-owned-hook');

        $this->assertSame($hook, \ACMS_Hook::singleton()->getHook('TestOwnedHook'), '2 回目の get() の後も登録されたまま');
        $this->assertSame(2, $hook->fired, '2 回とも発火した');
        $this->tearDown();
        $this->assertFalse(\ACMS_Hook::singleton()->getHook('TestOwnedHook'), 'テストの終わりには戻る');
    }

    #[Test]
    #[TestDox('名前空間の登録（ACMS_Namespace）は、get() の前後で同じに戻る')]
    public function 名前空間の登録はリクエストの前後で同じに戻る(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        $property = RequestRegistrations::namespaceProperty();
        $before = $property->getValue(\ACMS_Namespace::singleton());

        $this->get('/')->assertStatus(200);

        $this->assertSame($before, $property->getValue(\ACMS_Namespace::singleton()));
    }

    #[Test]
    #[TestDox('プラグインが公開の読み口（Context）で読んだ文脈は、複数のブログに対して、リクエストごとに正しい値になる')]
    public function 公開の読み口はリクエストごとに正しいブログの値を返す(): void
    {
        $otherHost = 'other.example.test';
        $otherBlogId = $this->seedBlog($otherHost, 'Other Blog');
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        PluginActivator::activate(ServiceProvider::class, $otherBlogId);

        $first = $this->get('/');
        $second = $this->withHost($otherHost)->get('/');
        $third = $this->withHost(self::HOST)->get('/');

        $first->assertHeader('X-Feature-Plugin-Blog', (string) $this->blogId)->assertSee('feature-plugin-hook blog=' . $this->blogId . ' view=top ');
        $second->assertHeader('X-Feature-Plugin-Blog', (string) $otherBlogId)->assertSee('feature-plugin-hook blog=' . $otherBlogId . ' view=top ');
        $third->assertHeader('X-Feature-Plugin-Blog', (string) $this->blogId)->assertSee('feature-plugin-hook blog=' . $this->blogId . ' ');
    }

    #[Test]
    #[TestDox('hook.init の直後（Hook::init() と同じ位置）では文脈が未確定で、確定した後の Hook（beforeGetFire）では Context で読める')]
    public function 文脈はHookのinitでは未確定で確定後のHookで読める(): void
    {
        $this->useTemplates(__DIR__ . '/../Fixtures/templates-module');
        \Config::set('tpl_top', 'module-top.html');
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        \ACMS_Hook::singleton()->attach('FeatureFixtureInit', new InitHook());
        InitHook::$observed = null;
        Hook::$beforeGetFire = null;

        $this->get('/');

        $this->assertSame(['blogDetermined' => false, 'blogIdThrows' => true, 'viewThrows' => true], InitHook::$observed);
        $this->assertNotNull(Hook::$beforeGetFire, 'beforeGetFire が発火している');
        $this->assertSame($this->blogId, Hook::$beforeGetFire['blogId']);
        $this->assertSame('top', Hook::$beforeGetFire['view']);
        $this->assertTrue(Hook::$beforeGetFire['entryDetermined']);
        $this->assertNull(Hook::$beforeGetFire['entryId']);
    }

    #[Test]
    #[TestDox('プラグインの init() が InjectTemplate へ足す登録は、get() のたびに積み上がらず、リクエストの後に残らない')]
    public function InjectTemplateの登録は積み上がらない(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);

        $this->get('/')->assertSee('inject=1 ');
        $this->get('/')->assertSee('inject=1 ');
        $third = $this->get('/');

        // 積み上がると、3 回目は inject=3 になる
        $third->assertSee('inject=1 ');
        $this->assertSame([], InjectTemplate::singleton()->get(ServiceProvider::INJECT_TYPE));
    }

    #[Test]
    #[TestDox('テストが自分で足した InjectTemplate の登録は、リクエストを挟んでも残り、tearDown で戻る')]
    public function テストが足した登録はリクエストで消えない(): void
    {
        InjectTemplate::singleton()->add('test-owned', 'mine.html');
        PluginActivator::activate(ServiceProvider::class, $this->blogId);

        $this->get('/');

        $this->assertSame(['mine.html'], InjectTemplate::singleton()->get('test-owned'));
        $this->tearDown();
        $this->assertSame([], InjectTemplate::singleton()->get('test-owned'));
    }

    #[Test]
    #[TestDox('プラグインの init() が override() した登録は、リクエストの中では効き、get() の後と tearDown の後には元へ戻る')]
    public function overrideの登録は戻る(): void
    {
        PluginActivator::activate(ServiceProvider::class, $this->blogId);
        $original = Application::make('markdown');

        $this->get('/')->assertSee('md=feature-fixture-markdown');
        $this->get('/')->assertSee('md=feature-fixture-markdown');

        $this->assertSame(get_class($original), get_class(Application::make('markdown')), 'リクエストの後に、差し替えが残らない');
        $this->assertNotInstanceOf(MarkerMarkdownConverter::class, Application::make('markdown'));
        $this->tearDown();
        $this->assertNotInstanceOf(MarkerMarkdownConverter::class, Application::make('markdown'));
    }
}
