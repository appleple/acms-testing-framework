<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Internal;

use Acms\Application;
use Acms\Services\Http\Kernel\CurrentContext;
use Acms\Services\Http\Kernel\RequestContext;
use Acms\TestingFramework\Internal\FakeConstants;
use Acms\Services\Facades\Login;
use Acms\Services\Login\Helper as LoginHelper;
use Acms\TestingFramework\Internal\GlobalStateGuard;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(GlobalStateGuard::class)]
final class GlobalStateGuardTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    #[Test]
    #[TestDox('capture() 以降に変えた $_SERVER・$GLOBALS・ステータス・コンフィグ・Login を restore() で元に戻す')]
    public function 変えた状態を元に戻す(): void
    {
        $guard = new GlobalStateGuard();
        $guard->capture();
        $originalLogin = Login::getInstance();
        $originalStatus = httpStatusCode();
        $originalHost = \Config::get('guard_test_key');

        $_SERVER['GUARD_TEST'] = 'changed';
        $GLOBALS['Q'] = 'changed';
        httpStatusCode('503 Service Unavailable');
        \Config::set('guard_test_key', 'changed');
        Login::swap($this->createMock(LoginHelper::class));
        $guard->restore();

        $this->assertArrayNotHasKey('GUARD_TEST', $_SERVER);
        $this->assertArrayNotHasKey('Q', $GLOBALS);
        $this->assertSame($originalStatus, httpStatusCode());
        $this->assertSame($originalHost, \Config::get('guard_test_key'));
        $this->assertSame($originalLogin, Login::getInstance());
    }

    #[Test]
    #[TestDox('もとから $GLOBALS にあった値は、変えても restore() で戻る')]
    public function もとからある値を戻す(): void
    {
        $GLOBALS['Q'] = 'original';
        $guard = new GlobalStateGuard();
        $guard->capture();

        $GLOBALS['Q'] = 'changed';
        $guard->restore();

        $this->assertSame('original', $GLOBALS['Q']);
        unset($GLOBALS['Q']);
    }

    #[Test]
    #[TestDox('Application::resetRequestState() で作り直した URL コンテキスト・GET・POST・Cookie が、restore() で戻る')]
    public function Applicationの値を作り直して戻す(): void
    {
        $guard = new GlobalStateGuard();
        $guard->capture();
        $app = \App::getInstance();
        assert($app instanceof Application);
        $previousContext = CurrentContext::set(new RequestContext(new FakeConstants(['REQUEST_PATH' => '/guard.html', 'HTTPS' => false])));
        $server = $_SERVER;
        [$get, $post, $cookie] = [$_GET, $_POST, $_COOKIE];
        try {
            $_GET = ['page' => '2'];
            $_POST = ['formToken' => 'abc'];
            $_COOKIE = ['sid' => 'cookie-value'];
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $app->resetRequestState();
            // 値は最初の呼び出しで作られる（カーネルの LoadRequestParameters が呼ぶ）
            $app->getGetParameter();
            $app->getCookieParameter();
            $app->getPostParameter();
            $installed = [
                $app->getQueryParameter()->get('path'),
                // モジュールが読む共有の Field にも、同じ値が見える
                \Field::singleton('query')->get('path'),
                \Field::singleton('get')->get('page'),
                \Field_Validation::singleton('post')->get('formToken'),
                $app->getPostParameter()->get('formToken'),
                \Field::singleton('cookie')->get('sid'),
            ];
        } finally {
            CurrentContext::set($previousContext);
            [$_SERVER, $_GET, $_POST, $_COOKIE] = [$server, $get, $post, $cookie];
        }
        $guard->restore();

        $this->assertSame(['/guard.html', '/guard.html', '2', 'abc', 'abc', 'cookie-value'], $installed);
        $this->assertNotSame('/guard.html', $app->getQueryParameter()->get('path'));
        $this->assertFalse($app->hasUnsafeRequestPath());
        $this->assertFalse(\Field_Validation::singleton('post')->isExists('formToken'), 'Field_Validation の共有の POST も戻る');
        $this->assertFalse(\Field::singleton('cookie')->isExists('sid'));
    }

    #[Test]
    #[TestDox('カーネルが書く Field::singleton（session・get・query・post）の中身と、$GLOBALS の GetQuery・contents・path も restore() で戻る')]
    public function 共有のFieldとグローバルを戻す(): void
    {
        $session = &\Field::singleton('session');
        $session->set('keep', 'original');
        // 全スイートでは、先に走ったテストが $GLOBALS に値を残していることがある。あったかどうかも含めて戻ることを確かめる
        $originals = [];
        foreach (['GetQuery', 'contents', 'path'] as $key) {
            $originals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $guard = new GlobalStateGuard();
        $guard->capture();

        $session->set('keep', 'changed');
        $session->set('added', 'x');
        \Field::singleton('get')->set('added', 'y');
        \Field::singleton('query')->set('added', 'z');
        \Field::singleton('post')->set('added', 'w');
        $GLOBALS['GetQuery'] = 'changed';
        $GLOBALS['contents'] = 'changed';
        $GLOBALS['path'] = 'changed';
        $guard->restore();

        $this->assertSame('original', \Field::singleton('session')->get('keep'));
        foreach (['session', 'get', 'query', 'post'] as $key) {
            $this->assertFalse(\Field::singleton($key)->isExists('added'), $key);
        }
        foreach ($originals as $key => [$existed, $value]) {
            $this->assertSame($existed, array_key_exists($key, $GLOBALS), $key);
            $this->assertSame($value, $GLOBALS[$key] ?? null, $key);
        }
    }

    #[Test]
    #[TestDox('resetSession() は共有のセッションを空にし、restore() で元の中身へ戻る')]
    public function セッションを空にして戻す(): void
    {
        $session = &\Field::singleton('session');
        $session->set('uid', '1');
        $guard = new GlobalStateGuard();
        $guard->capture();

        $guard->resetSession();
        $afterReset = \Field::singleton('session')->get('uid');
        $guard->restore();

        $this->assertSame('', $afterReset);
        $this->assertSame('1', \Field::singleton('session')->get('uid'));
        \Field::singleton('session')->delete('uid');
    }

    #[Test]
    #[TestDox('restore() は、書き換えたコンフィグを覚えた関数キャッシュ（ACMS_RAM）を捨てる')]
    public function 関数キャッシュを捨てる(): void
    {
        $guard = new GlobalStateGuard();
        $guard->capture();
        \ACMS_RAM::cacheMethod('strval', [1]);
        $reflection = new \ReflectionProperty(\ACMS_RAM::class, 'funcCache');
        $cachedBefore = $reflection->getValue() !== [];

        $guard->restore();

        $this->assertTrue($cachedBefore);
        $this->assertSame([], $reflection->getValue());
    }

    #[Test]
    #[TestDox('capture() 以降に ACMS_Namespace へ足した名前空間は、restore() で戻る')]
    public function 名前空間の登録を戻す(): void
    {
        $property = new \ReflectionProperty(\ACMS_Namespace::class, '_namespaces');
        $before = $property->getValue(\ACMS_Namespace::singleton());
        $guard = new GlobalStateGuard();
        $guard->capture();

        \ACMS_Namespace::singleton()->addNamespace('GuardTestNamespace', 'psr4');
        $added = $property->getValue(\ACMS_Namespace::singleton());
        $guard->restore();

        $this->assertCount(count($before) + 1, $added);
        $this->assertSame($before, $property->getValue(\ACMS_Namespace::singleton()));
    }

    #[Test]
    #[TestDox('capture() していなければ restore() は何もしない（2 回呼んでも壊れない）')]
    public function 控えていなければ何もしない(): void
    {
        $guard = new GlobalStateGuard();
        $guard->capture();
        $guard->restore();
        $_SERVER['GUARD_TEST'] = 'kept';

        $guard->restore();

        $this->assertSame('kept', $_SERVER['GUARD_TEST']);
    }
}
