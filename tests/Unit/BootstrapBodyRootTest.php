<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\Bootstrap;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * `Bootstrap::resolveBodyRoot()` が 2 通りのレイアウトを正しく判定することを保証する。
 *
 * - モノレポ / git 作業ツリー: 本体は `<ACMS_ROOT>/ablogcms/` 配下。
 * - 配布物・本体同梱 Docker イメージ（appleple/acms）: 本体は `ACMS_ROOT` 直下（`ablogcms/` 無し）。
 *
 * 後者で `/ablogcms/` を前置してしまうと `.../php/vendor/autoload.php` が見つからず fatal になる。
 */
final class BootstrapBodyRootTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }
        $this->tempDirs = [];

        parent::tearDown();
    }

    private function tempRoot(): string
    {
        $dir = $this->createTempDirectory('acms_body_');
        $this->tempDirs[] = $dir;

        return $dir;
    }

    #[Test]
    #[TestDox('ablogcms/php があるレイアウト（モノレポ）では <root>/ablogcms を本体ルートにする')]
    public function モノレポレイアウトを検出する(): void
    {
        $root = $this->tempRoot();
        \mkdir($root . '/ablogcms/php', 0777, true);

        $this->assertSame($root . '/ablogcms', Bootstrap::resolveBodyRoot($root));
    }

    #[Test]
    #[TestDox('本体が直下にあるレイアウト（同梱イメージ）では root をそのまま本体ルートにする')]
    public function 同梱イメージレイアウトを検出する(): void
    {
        $root = $this->tempRoot();
        \mkdir($root . '/php', 0777, true); // ablogcms/ は作らない

        $this->assertSame($root, Bootstrap::resolveBodyRoot($root));
    }

    #[Test]
    #[TestDox('末尾スラッシュは正規化してから判定する')]
    public function 末尾スラッシュを正規化する(): void
    {
        $root = $this->tempRoot();
        \mkdir($root . '/php', 0777, true);

        $this->assertSame($root, Bootstrap::resolveBodyRoot($root . '/'));
    }
}
