<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * 基底クラスの一時ファイル・一時ディレクトリのヘルパー（プラグインのテストが使える）の振る舞い
 */
#[CoversClass(TestCase::class)]
final class TestCaseTempHelpersTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_link($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirectory($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }

    #[Test]
    #[TestDox('createTempDirectory() は、接頭辞付きの空のディレクトリを一時ディレクトリの下に作る')]
    public function createTempDirectoryCreatesDirectoryWithPrefix(): void
    {
        $dir = $this->createTempDirectory('acms_helper_test_');
        $this->created[] = $dir;

        $this->assertDirectoryExists($dir);
        $this->assertStringStartsWith(sys_get_temp_dir() . '/acms_helper_test_', $dir);
        $this->assertSame([], array_values(array_diff(scandir($dir) ?: [], ['.', '..'])));
    }

    #[Test]
    #[TestDox('createTempFile() は、内容・接頭辞・拡張子を指定した一時ファイルを作る')]
    public function createTempFileWritesContent(): void
    {
        $file = $this->createTempFile('hello', 'acms_helper_test_', '.csv');
        $this->created[] = $file;

        $this->assertFileExists($file);
        $this->assertSame('hello', file_get_contents($file));
        $this->assertStringStartsWith(sys_get_temp_dir() . '/acms_helper_test_', $file);
        $this->assertStringEndsWith('.csv', $file);
    }

    #[Test]
    #[TestDox('removeDirectory() は、入れ子のディレクトリとファイルをまとめて消す')]
    public function removeDirectoryRemovesNestedContents(): void
    {
        $dir = $this->createTempDirectory('acms_helper_test_');
        $this->created[] = $dir;
        mkdir($dir . '/a/b', 0755, true);
        file_put_contents($dir . '/top.txt', 'x');
        file_put_contents($dir . '/a/b/deep.txt', 'y');

        $this->removeDirectory($dir);

        $this->assertDirectoryDoesNotExist($dir);
    }

    #[Test]
    #[TestDox('removeDirectory() は、存在しないディレクトリを渡されても何もしない')]
    public function removeDirectoryIgnoresMissingDirectory(): void
    {
        $this->removeDirectory(sys_get_temp_dir() . '/acms_helper_test_missing_' . uniqid());

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[TestDox('removeDirectory() は、ディレクトリへのシンボリックリンクを辿らず、リンク自体だけを消す（リンク先の中身は残る）')]
    public function removeDirectoryDoesNotFollowSymlinkedDirectory(): void
    {
        $target = $this->createTempDirectory('acms_helper_test_target_');
        $this->created[] = $target;
        file_put_contents($target . '/keep.txt', 'keep');
        $dir = $this->createTempDirectory('acms_helper_test_');
        $this->created[] = $dir;
        symlink($target, $dir . '/link');

        $this->removeDirectory($dir);

        $this->assertDirectoryDoesNotExist($dir);
        $this->assertFileExists($target . '/keep.txt');
    }

    #[Test]
    #[TestDox('removeDirectory() は、引数自体がシンボリックリンクのとき、リンクだけを消してリンク先を残す')]
    public function removeDirectoryRemovesOnlyTheLinkWhenGivenSymlink(): void
    {
        $target = $this->createTempDirectory('acms_helper_test_target_');
        $this->created[] = $target;
        file_put_contents($target . '/keep.txt', 'keep');
        $link = sys_get_temp_dir() . '/acms_helper_test_link_' . uniqid();
        symlink($target, $link);
        $this->created[] = $link;

        $this->removeDirectory($link);

        $this->assertFalse(is_link($link));
        $this->assertFileExists($target . '/keep.txt');
    }
}
