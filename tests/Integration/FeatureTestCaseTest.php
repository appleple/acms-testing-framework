<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration;

use Acms\Services\Facades\Database;
use Acms\TestingFramework\FeatureTestCase;
use Acms\TestingFramework\Http\TestResponse;
use Acms\TestingFramework\Internal\AssetBinding;
use Acms\TestingFramework\Internal\FakeConstants;
use Acms\TestingFramework\Internal\FakeHeaders;
use Acms\TestingFramework\Internal\FakeHooks;
use Acms\TestingFramework\Internal\FakeOutput;
use Acms\TestingFramework\Internal\FakeRuntime;
use Acms\TestingFramework\Internal\FakeSessionEngine;
use Acms\TestingFramework\Internal\FakeStatusCode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * 公開の窓口（`get()` / `post()` / `actingAs()` ほか）だけを使って、プラグインの開発者が書くのと同じ形で確かめる
 */
#[CoversClass(FeatureTestCase::class)]
#[CoversClass(TestResponse::class)]
#[CoversClass(AssetBinding::class)]
#[CoversClass(FakeConstants::class)]
#[CoversClass(FakeHeaders::class)]
#[CoversClass(FakeHooks::class)]
#[CoversClass(FakeOutput::class)]
#[CoversClass(FakeRuntime::class)]
#[CoversClass(FakeSessionEngine::class)]
#[CoversClass(FakeStatusCode::class)]
final class FeatureTestCaseTest extends FeatureTestCase
{
    private const HOST = 'plugin.example.test';

    private int $blogId = 0;

    private int $adminId = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../Fixtures/FeatureHandlers.php';
    }

    protected function setUpSite(): void
    {
        $this->blogId = $this->seedBlog(self::HOST, 'Plugin Blog');
        $this->adminId = $this->seedUser($this->blogId, 'administrator', ['user_name' => 'Alice']);
        $this->withHost(self::HOST);
    }

    #[Test]
    #[TestDox('既定の最小のテーマで、ドメインで解決したブログのトップページを描く')]
    public function 既定のテーマでページを描く(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('top blog=Plugin Blog');
    }

    #[Test]
    #[TestDox('絶対 URL を渡すと、withHost() とは別のブログを開ける')]
    public function 絶対URLで別のブログを開く(): void
    {
        $this->seedBlog('other.example.test', 'Other Blog');

        $this->get('http://other.example.test/')->assertSee('top blog=Other Blog');
        $this->get('/')->assertSee('top blog=Plugin Blog');
    }

    #[Test]
    public function クエリは配列で渡せる(): void
    {
        $this->get('/', ['page' => 2, 'keyword' => 'abc'])->assertStatus(200)->assertSee('page=2')->assertSee('keyword=abc');
    }

    #[Test]
    public function 存在しないページは404(): void
    {
        $this->get('/no-such-category/no-such.html')->assertStatus(404)->assertSee('feature-theme-not-found');
    }

    #[Test]
    #[TestDox('useTemplates() で足した自分のテンプレートで描ける。テーマ名はテストごとに違う')]
    public function 自分のテンプレートを使う(): void
    {
        $this->useTemplates(__DIR__ . '/../Fixtures/templates');
        \Config::set('tpl_top', 'plugin-top.html');

        $this->get('/', ['x' => 'y'])->assertStatus(200)->assertSee('plugin-page blog=Plugin Blog')->assertSee('query=')->assertDontSee('top blog=');
        $this->assertStringStartsWith('feature-', $this->themeName());
    }

    #[Test]
    public function 存在しないテンプレートのディレクトリは指定できない(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->useTemplates(__DIR__ . '/no-such-directory');
    }

    #[Test]
    #[TestDox('サブディレクトリごとテンプレートを足せる')]
    public function サブディレクトリを含めて足す(): void
    {
        $directory = $this->createTempDirectory('feature_templates_');
        mkdir($directory . '/sub');
        file_put_contents($directory . '/sub/page.html', 'nested-template');
        file_put_contents($directory . '/index.html', 'overridden-index');
        try {
            $this->useTemplates($directory);
            \Config::set('tpl_top', 'sub/page.html');

            $this->get('/')->assertSee('nested-template');
        } finally {
            // アサーションが失敗しても一時ディレクトリを残さない
            $this->removeDirectory($directory);
        }
    }

    #[Test]
    #[TestDox('actingAs() で以後のリクエストはログイン中になり、actingAsGuest() で戻る。続けて流しても取り違えない')]
    public function ログイン状態(): void
    {
        $this->useTemplates(__DIR__ . '/../Fixtures/templates');
        \Config::set('tpl_top', 'plugin-top.html');

        $guest = $this->get('/');
        $loggedIn = $this->actingAs($this->adminId)->get('/');
        $stillLoggedIn = $this->get('/');
        $guestAgain = $this->actingAsGuest()->get('/');

        $guest->assertSee('user=[]');
        $loggedIn->assertSee('user=[Alice]');
        $stillLoggedIn->assertSee('user=[Alice]');
        $guestAgain->assertSee('user=[]');
    }

    #[Test]
    #[TestDox('権限の無い人は管理画面が 403、未ログインはログインへの転送、管理者は 200')]
    public function 管理画面の認可(): void
    {
        $subscriberId = $this->seedUser($this->blogId, 'subscriber');
        $path = '/bid/' . $this->blogId . '/admin/backup_index/';

        $this->get($path)->assertRedirect()->assertHeader('Location');
        $this->actingAs($subscriberId)->get($path)->assertStatus(403);
        $this->actingAs($this->adminId)->get($path)->assertStatus(200)->assertSee('Plugin Blog');
    }

    #[Test]
    #[TestDox('POST は CSRF トークンを自動で付ける。ハンドラが返した JSON を応答として受け取れる')]
    public function POSTでJSONを受け取る(): void
    {
        $this->post('/', ['ACMS_POST_FeatureFixture_Json' => 'on', 'name' => 'plugin'])
            ->assertStatus(200)
            ->assertJson(['ok' => true, 'form' => ['name' => 'plugin']]);
    }

    private function blogName(): string
    {
        $select = \SQL::newSelect('blog');
        $select->addSelect('blog_name');
        $select->addWhereOpr('blog_id', $this->blogId);

        return (string) Database::query($select->get(dsn()), 'one');
    }

    #[Test]
    #[TestDox('CSRF トークンを付けない指定では、ハンドラが動かず、副作用（DB の行の更新）も起きない')]
    public function トークン無しのPOSTは処理されない(): void
    {
        $form = ['ACMS_POST_FeatureFixture_Save' => 'on', 'blog_id' => $this->blogId, 'name' => 'Changed'];

        $response = $this->post('/', $form, [], false);

        $response->assertStatus(200)->assertDontSee('"saved"');
        $this->assertSame('Plugin Blog', $this->blogName());
    }

    #[Test]
    #[TestDox('不正なトークンのPOSTも、ハンドラが動かず副作用が起きない。正しいトークンなら更新される')]
    public function 不正なトークンのPOSTは処理されない(): void
    {
        $form = ['ACMS_POST_FeatureFixture_Save' => 'on', 'blog_id' => $this->blogId, 'name' => 'Changed'];

        $this->post('/', $form + ['formToken' => 'wrong'])->assertDontSee('"saved"');
        $this->assertSame('Plugin Blog', $this->blogName());

        $this->post('/', $form)->assertJson(['saved' => true]);
        $this->assertSame('Changed', $this->blogName());
    }

    #[Test]
    #[TestDox('ログイン中の POST もトークンが自動で付き、続けて未ログインに戻しても取り違えない')]
    public function ログイン中のPOST(): void
    {
        $this->actingAs($this->adminId)->post('/', ['ACMS_POST_FeatureFixture_Json' => 'on'])->assertJson(['ok' => true]);
        $this->actingAsGuest()->post('/', ['ACMS_POST_FeatureFixture_Json' => 'on'])->assertJson(['ok' => true]);
    }

    #[Test]
    #[TestDox('関数の奥の die403() は、POST の戻り値の 403 の応答として受け取れる')]
    public function 関数の奥の403を受け取る(): void
    {
        $this->post('/', ['ACMS_POST_FeatureFixture_Forbidden' => 'on'])->assertStatus(403)->assertSee('403 Forbidden');
    }

    #[Test]
    #[TestDox('redirect() は、POST の戻り値の転送の応答として受け取れる')]
    public function 関数の奥の転送を受け取る(): void
    {
        $this->post('/', ['ACMS_POST_FeatureFixture_Redirect' => 'on'])->assertRedirect('http://' . self::HOST . '/done/')->assertRedirect('/done/');
    }

    #[Test]
    #[TestDox('相対パスで転送先を確かめるとき、別のホストへの転送は通らない（絶対 URL なら確かめられる）')]
    public function 別のホストへの転送はパスでは通らない(): void
    {
        $response = $this->post('/', ['ACMS_POST_FeatureFixture_ExternalRedirect' => 'on']);

        $response->assertRedirect('https://external.example.test/done/');
        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $response->assertRedirect('/done/');
    }

    #[Test]
    #[TestDox('assertOk() / assertForbidden() で、ステータスを短く確かめられる')]
    public function ステータスの短縮(): void
    {
        $this->get('/')->assertOk();
        $this->post('/', ['ACMS_POST_FeatureFixture_Forbidden' => 'on'])->assertForbidden();
    }

    #[Test]
    #[TestDox('withHeaders() / withCookies() は以後のリクエストに付き、リクエストごとのヘッダーはその 1 回だけ。同じ名前は置き換える')]
    public function ヘッダーとCookie(): void
    {
        $form = ['ACMS_POST_FeatureFixture_Json' => 'on'];
        $this->withHeaders(['X-Feature' => 'persistent'])->withCookies(['feature_cookie' => 'cookie-a']);

        $this->post('/', $form)->assertJson(['header' => 'persistent', 'cookie' => 'cookie-a']);
        $this->post('/', $form, ['X-Feature' => 'once'])->assertJson(['header' => 'once']);
        $this->post('/', $form)->assertJson(['header' => 'persistent']);
        $this->withHeaders(['X-Feature' => 'replaced'])->withCookies(['feature_cookie' => 'cookie-b']);
        $this->post('/', $form)->assertJson(['header' => 'replaced', 'cookie' => 'cookie-b']);
    }

    #[Test]
    #[TestDox('Content-Type のヘッダーは HTTP_ を付けずに渡る')]
    public function ContentTypeヘッダー(): void
    {
        $this->get('/', [], ['Content-Type' => 'text/plain'])->assertStatus(200);
        $this->assertSame('text/plain', $_SERVER['CONTENT_TYPE']);
    }

    #[Test]
    #[TestDox('テストのあとで、コンフィグとステータスは元に戻る（次のテストの結果が変わらない）')]
    public function 前のテストの状態を引き継がない(): void
    {
        $this->assertNotSame('plugin-top.html', config('tpl_top'));
        $this->assertSame('200 OK', httpStatusCode());
        \Config::set('tpl_top', 'plugin-top.html');
        $this->get('/no-such-category/no-such.html')->assertStatus(404);
    }
}
