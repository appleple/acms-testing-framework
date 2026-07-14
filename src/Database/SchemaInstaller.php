<?php

namespace Acms\TestingFramework\Database;

use Acms\Services\Update\Database\Schema;
use Throwable;

/**
 * テスト用データベースのスキーマ（テーブル・インデックス）を無人で作成する。
 *
 * 本体のインストーラ（ブラウザウィザード）や管理者アカウントは作らない。
 * 統合テストの前提となるのは「スキーマが存在すること」だけで、テストデータは
 * Seeder が作る。`bin/acms-create-database` から呼び出される。
 *
 * 事前に {@see \Acms\TestingFramework\Bootstrap::boot()} で本体を初期化しておくこと。
 */
final class SchemaInstaller
{
    /**
     * スキーマを作成する。
     *
     * @return int 成功時 0、失敗時 1（プロセスの終了コードとして利用できる）。
     */
    public static function run(): int
    {
        echo '--------------------------------' . "\n";
        echo 'テストデータベースのセットアップを開始します' . "\n";
        echo '--------------------------------' . "\n";

        try {
            $dsn = \dsn();

            echo '接続情報:' . "\n";
            echo '  Host: ' . $dsn['host'] . "\n";
            echo '  Database: ' . $dsn['name'] . "\n";
            echo '  Prefix: ' . $dsn['prefix'] . "\n";

            $schema = new Schema($dsn);

            $tablesToCreate = $schema->compareTables();

            if (count($tablesToCreate) === 0) {
                echo '✓ すべてのテーブルが既に存在します。' . "\n";
                echo '  作成済みテーブル数: ' . count($schema->listUp($schema->define)) . "\n";
            } else {
                echo '作成するテーブル数: ' . count($tablesToCreate) . "\n";
                $schema->createTables($tablesToCreate, $schema->indexDefine);
            }

            echo '--------------------------------' . "\n";
            echo 'テストデータベースのセットアップを完了しました' . "\n";
            echo '--------------------------------' . "\n";
        } catch (Throwable $e) {
            echo '--------------------------------' . "\n";
            echo 'テストデータベースのセットアップに失敗しました' . "\n";
            echo '  ' . $e->getMessage() . "\n";
            echo '  ' . $e->getFile() . ':' . $e->getLine() . "\n";
            echo '--------------------------------' . "\n";
            return 1;
        }

        return 0;
    }
}
