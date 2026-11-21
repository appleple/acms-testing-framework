<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * 前のテストが書いた HTTP ステータス（`httpStatusCode()` の static）を、次のテストへ持ち越さない
 *
 * REST API の Kernel は `isErrorHttpStatus()` でルートの解決を止めるため、404 のまま終わったテストの後ろで、無関係なテストが 404 になっていた。
 */
final class TestCaseHttpStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        httpStatusCode('200 OK');
        parent::tearDown();
    }

    #[Test]
    #[TestDox('setUp() が、前のテストが書いたエラーのステータスを 200 に戻す')]
    public function setUpResetsStatus(): void
    {
        httpStatusCode('404 Not Found');

        $this->setUp();

        $this->assertSame('200 OK', httpStatusCode());
        $this->assertFalse(isErrorHttpStatus());
    }

    #[Test]
    #[TestDox('tearDown() が、このテストが書いたエラーのステータスを 200 に戻す')]
    public function tearDownResetsStatus(): void
    {
        httpStatusCode('403 Forbidden');

        $this->tearDown();

        $this->assertSame('200 OK', httpStatusCode());
    }
}
