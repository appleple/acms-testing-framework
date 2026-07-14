<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\Bootstrap;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * CLI エントリ `bin/acms-create-database` が Bootstrap の実在メソッドだけを呼ぶことを保証する。
 *
 * v3.2.0 では存在しない `Bootstrap::initialize()` を呼んでおり、プラグイン CI の
 * `vendor/bin/acms-create-database` が「Call to undefined method」で fatal になっていた
 * （公開エントリは `Bootstrap::boot()`）。同種のタイポが再び出荷されないよう、bin が参照する
 * Bootstrap のメソッドがすべて実在することを静的に検証する。
 */
final class CreateDatabaseBinTest extends TestCase
{
    private function binSource(): string
    {
        $path = __DIR__ . '/../../bin/acms-create-database';
        $source = \file_get_contents($path);
        $this->assertNotFalse($source, "bin を読み込めない: {$path}");

        return $source;
    }

    #[Test]
    #[TestDox('bin/acms-create-database は公開エントリ Bootstrap::boot() を呼ぶ')]
    public function bootを呼ぶ(): void
    {
        $this->assertStringContainsString('Bootstrap::boot(', $this->binSource());
    }

    #[Test]
    #[TestDox('bin が参照する Bootstrap のメソッドはすべて実在する')]
    public function 実在するメソッドだけを呼ぶ(): void
    {
        \preg_match_all('/Bootstrap::(\w+)\s*\(/', $this->binSource(), $matches);
        $methods = \array_unique($matches[1]);

        $this->assertNotEmpty($methods, 'bin が Bootstrap の呼び出しを含まない');
        foreach ($methods as $method) {
            $this->assertTrue(
                \method_exists(Bootstrap::class, $method),
                "Bootstrap に存在しないメソッドを呼んでいる: Bootstrap::{$method}()"
            );
        }
    }
}
