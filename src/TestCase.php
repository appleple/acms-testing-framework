<?php

namespace Acms\TestingFramework;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * 全テストの基底クラス
 *
 * 共通の初期化処理やヘルパーメソッドを定義します。
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * テスト前の初期化処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        // 共通の初期化処理があればここに記述
    }

    /**
     * テスト後のクリーンアップ処理
     */
    protected function tearDown(): void
    {
        // 共通のクリーンアップ処理があればここに記述
        parent::tearDown();
    }

    /**
     * ディレクトリを再帰的に削除するヘルパーメソッド
     *
     * @param string $dir 削除するディレクトリのパス
     * @return void
     */
    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 一時ディレクトリを作成するヘルパーメソッド
     *
     * @param string $prefix ディレクトリ名のプレフィックス
     * @return string 作成されたディレクトリのパス
     */
    protected function createTempDirectory(string $prefix = 'acms_test_'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . uniqid();
        mkdir($dir, 0755, true);
        return $dir;
    }

    /**
     * 一時ファイルを作成するヘルパーメソッド
     *
     * @param string $content ファイルの内容
     * @param string $prefix ファイル名のプレフィックス
     * @param string $suffix ファイル名のサフィックス
     * @return string 作成されたファイルのパス
     */
    protected function createTempFile(string $content = '', string $prefix = 'acms_test_', string $suffix = '.txt'): string
    {
        $file = sys_get_temp_dir() . '/' . $prefix . uniqid() . $suffix;
        file_put_contents($file, $content);
        return $file;
    }
}
