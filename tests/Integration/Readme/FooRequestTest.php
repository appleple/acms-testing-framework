<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Readme;

use Acms\Services\Facades\Database;
use Acms\Plugins\Foo\Tests\RecordingMailer;
use Acms\TestingFramework\FeatureTestCase;
use Acms\TestingFramework\Helpers\PluginActivator;
use PHPUnit\Framework\Attributes\Test;

/**
 * README の「機能テスト」の例を、そのまま写したテスト（README の例と食い違わないよう、README を直したらここも直す）
 *
 * `setUpBeforeClass()` だけは、このリポジトリのテストのためのもの（ハンドラとプラグインのクラスを Fixtures から読む。
 * 実際のプラグインでは、composer の autoload で読まれる）。
 */
final class FooRequestTest extends FeatureTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../Fixtures/Readme/FooHandlers.php';
        require_once __DIR__ . '/../../Fixtures/Readme/FooPlugin.php';
        require_once __DIR__ . '/../../Fixtures/Readme/RecordingMailer.php';
    }

    private int $blogId;
    private int $editorId;

    // テーマ・コンフィグを整えたあと、DB のトランザクションの中で呼ばれます
    protected function setUpSite(): void
    {
        $this->blogId = $this->seedBlog('foo.example.test', 'Foo Blog');   // ドメインで解決されるブログ
        $this->editorId = $this->seedUser($this->blogId, 'editor', ['user_name' => 'Alice']);
        $this->withHost('foo.example.test');                                // 相対パスのときのホスト
    }

    private function blogName(): string
    {
        $select = \SQL::newSelect('blog');
        $select->addSelect('blog_name');
        $select->addWhereOpr('blog_id', $this->blogId);

        return (string) Database::query($select->get(dsn()), 'one');
    }

    #[Test]
    public function 権限が無ければ403で何も書き込まれない(): void
    {
        // 未ログインの POST。ハンドラの中の die403() が、403 の応答として返る
        $this->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'])->assertForbidden();

        $this->assertSame('Foo Blog', $this->blogName());
    }

    #[Test]
    public function 保存したら一覧へ転送し、タイトルを更新する(): void
    {
        $this->actingAs($this->editorId)
            ->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'])
            ->assertRedirect('/foo/');       // 絶対 URL でも、`/` で始まるパスでも指定できる

        $this->assertSame('new', $this->blogName());
    }

    #[Test]
    public function JSONを返す(): void
    {
        $this->actingAs($this->editorId)
            ->post('/', ['ACMS_POST_Foo_Search' => 'on', 'q' => 'abc'])
            ->assertOk()
            ->assertJson(['ok' => true, 'items' => ['count' => 1]]);   // 連想配列は部分一致、リストは完全一致
    }

    #[Test]
    public function トークンが無いPOSTは処理されず何も書き込まれない(): void
    {
        // 第 4 引数 false で CSRF トークンを付けない。付ける値を変えるときは、フォームに `formToken` を渡す
        $this->actingAs($this->editorId)
            ->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'], [], false);

        $this->assertSame('Foo Blog', $this->blogName());
    }

    #[Test]
    public function ログインしているときだけ名前を出す(): void
    {
        $this->useTemplates(__DIR__ . '/../templates');     // サブディレクトリごと、テストごとの一時テーマへコピーされる
        \Config::set('tpl_top', 'foo-top.html');

        $this->get('/')->assertSee('user=[]');
        $this->actingAs($this->editorId)->get('/')->assertSee('user=[Alice]');   // 以後のリクエストはログイン中
        $this->actingAsGuest()->get('/')->assertSee('user=[]');                 // 未ログインに戻す
    }

    #[Test]
    public function 有効化したプラグインのミドルウェアとHookが動く(): void
    {
        PluginActivator::activate('Foo', $this->blogId);   // Acms\Plugins\Foo\ServiceProvider

        $this->get('/')->assertHeader('X-Foo-Plugin', 'on')->assertSee('<!-- foo-hook -->');
    }

    #[Test]
    public function 公開の読み口は複数のブログでリクエストごとに正しい値を返す(): void
    {
        $otherBlogId = $this->seedBlog('bar.example.test', 'Bar Blog');
        PluginActivator::activate('Foo', $this->blogId);
        PluginActivator::activate('Foo', $otherBlogId);

        $this->get('/')->assertSee('<!-- foo-blog=' . $this->blogId . ' -->');
        $this->withHost('bar.example.test')->get('/')->assertSee('<!-- foo-blog=' . $otherBlogId . ' -->');
    }

    #[Test]
    public function メールを送るプラグインは偽物に差し替えてから使う(): void
    {
        RecordingMailer::$sent = [];
        \App::getInstance()->override('mailer', RecordingMailer::class, ['owner' => 'foo-test']);

        $this->actingAs($this->editorId)->post('/', ['ACMS_POST_Foo_Notify' => 'on'])->assertOk();

        $this->assertSame([['to@example.test']], RecordingMailer::$sent);
    }
}
