<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class EnvFunctionTest extends TestCase
{
    private const KEY = 'ACMS_TESTING_ENV_FUNCTION_TEST';

    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../../src/functions.php';

        unset($_ENV[self::KEY]);
        putenv(self::KEY);
    }

    protected function tearDown(): void
    {
        unset($_ENV[self::KEY]);
        putenv(self::KEY);

        parent::tearDown();
    }

    #[Test]
    #[TestDox('$_ENV に値がある場合はその値を返す')]
    public function envの値を優先して返す(): void
    {
        $_ENV[self::KEY] = 'from-env-superglobal';

        $this->assertSame('from-env-superglobal', env(self::KEY, 'default'));
    }

    #[Test]
    #[TestDox('$_ENV に無く getenv() にある場合はその値を返す')]
    public function getenvへフォールバックする(): void
    {
        putenv(self::KEY . '=from-getenv');

        $this->assertSame('from-getenv', env(self::KEY, 'default'));
    }

    #[Test]
    #[TestDox('どちらにも無い場合はデフォルト値を返す')]
    public function デフォルト値を返す(): void
    {
        $this->assertSame('default', env(self::KEY, 'default'));
    }
}
