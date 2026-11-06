<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration;

use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Bootstrap::boot() が定義する定数の特徴付けテスト。
 *
 * 本体（standalone.php / main.php）がリクエスト定数を追加・変更したときに
 * Bootstrap（および共有元の ablogcms/php/config/cli_constants.php）が追従できて
 * いないと、本テストか後続のテスト全体の bootstrap 段階で失敗する。
 */
final class BootstrapTest extends TestCase
{
    #[Test]
    #[TestDox('config.server.php 相当の定数が定義されている')]
    public function configServerPhp相当の定数が定義されている(): void
    {
        $this->assertTrue(defined('DB_HOST'));
        $this->assertTrue(defined('DB_PREFIX'));
        $this->assertTrue(defined('THEMES_DIR'));
        $this->assertTrue(defined('PHP_DIR'));
        $this->assertTrue(defined('HOOK_ENABLE'));
    }

    #[Test]
    #[TestDox('standalone.php と共有するリクエスト定数が定義されている')]
    public function standalonePhpと共有するリクエスト定数が定義されている(): void
    {
        $this->assertTrue(defined('BID'));
        $this->assertTrue(defined('EID'));
        $this->assertTrue(defined('CID'));
        $this->assertTrue(defined('UID'));
        $this->assertTrue(defined('RVID'));
        $this->assertTrue(defined('SYSTEM_GENERATED_DATETIME'));
    }

    #[Test]
    #[TestDox('Bootstrap 固有のテスト用スタブ定数が定義されている')]
    public function bootstrap固有のスタブ定数が定義されている(): void
    {
        $this->assertTrue(defined('LICENSE_EDITION'));
        $this->assertTrue(defined('ACMS_POST'));
        $this->assertTrue(defined('ROOT_TPL'));
    }

    #[Test]
    #[TestDox('Application コンテナが起動し dsn() が DB_* 定数から DSN を組み立てられる')]
    public function applicationコンテナからDSNを取得できる(): void
    {
        $dsn = dsn();

        $this->assertSame(DB_HOST, $dsn['host']);
        $this->assertSame(DB_NAME, $dsn['name']);
        $this->assertSame(DB_PREFIX, $dsn['prefix']);
    }

    #[Test]
    #[TestDox('同梱の共有 bootstrap.php をプラグインが直接指せる（存在し、冪等に読み込める）')]
    public function 共有bootstrapエントリが冪等に読み込める(): void
    {
        // packages/backend/testing-framework/tests/Integration → パッケージルート
        $entry = dirname(__DIR__, 2) . '/bootstrap.php';
        $this->assertFileExists($entry);

        // すでに boot 済み・オートローダ登録済みの状態で再読込しても、class_exists 分岐で
        // 消費側 autoload の require を避け、Bootstrap::boot() は冪等に早期 return する。
        require $entry;

        $this->assertTrue(defined('DB_HOST'));
    }
}
