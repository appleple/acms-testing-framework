<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Internal;

use Acms\TestingFramework\Internal\StaleTemporaryFiles;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(StaleTemporaryFiles::class)]
final class StaleTemporaryFilesTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/acms-stale-test-' . bin2hex(random_bytes(6));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        StaleTemporaryFiles::delete($this->root);
        parent::tearDown();
    }

    #[Test]
    #[TestDox('更新から 1 日以上たった名前の合うものだけを、中身ごと消し、新しいもの・名前の合わないものは残す')]
    public function 古い残骸だけを消す(): void
    {
        $stale = $this->root . '/feature-0123456789ab';
        mkdir($stale . '/nested', 0777, true);
        file_put_contents($stale . '/nested/file.html', 'x');
        $fresh = $this->root . '/feature-ba9876543210';
        mkdir($fresh);
        $otherName = $this->root . '/feature-theme';
        mkdir($otherName);
        touch($stale, time() - StaleTemporaryFiles::AFTER_SECONDS - 10);
        touch($otherName, time() - StaleTemporaryFiles::AFTER_SECONDS - 10);

        StaleTemporaryFiles::remove($this->root . '/feature-*', '/^feature-[0-9a-f]{12}$/');

        $this->assertDirectoryDoesNotExist($stale);
        $this->assertDirectoryExists($fresh);
        $this->assertDirectoryExists($otherName);
    }

    #[Test]
    #[TestDox('ファイルも消せ、シンボリックリンクはたどらずリンクだけを消す')]
    public function ファイルとリンクを消す(): void
    {
        $target = $this->root . '/target';
        mkdir($target);
        file_put_contents($target . '/keep.txt', 'x');
        $link = $this->root . '/link';
        symlink($target, $link);
        $file = $this->root . '/a.json';
        file_put_contents($file, '{}');

        StaleTemporaryFiles::delete($link);
        StaleTemporaryFiles::delete($file);
        StaleTemporaryFiles::delete($this->root . '/missing');

        $this->assertFalse(is_link($link));
        $this->assertFileDoesNotExist($file);
        $this->assertFileExists($target . '/keep.txt');
    }
}
