<?php

namespace Acms\TestingFramework;

use Acms\Services\Facades\Database;
use Acms\TestingFramework\Seeder\BlogSeeder;
use Acms\TestingFramework\Seeder\CategorySeeder;
use Acms\TestingFramework\Seeder\EntrySeeder;
use Acms\TestingFramework\Seeder\UserSeeder;
use SQL;

/**
 * データベースを使用するテストの基底クラス
 *
 * ## テストデータの作成方法
 *
 * Seederクラスを使用してテストデータを作成できます：
 *
 * ```php
 * use Acms\TestingFramework\Seeder\BlogSeeder;
 * use Acms\TestingFramework\Seeder\CategorySeeder;
 *
 * protected function setUpDatabase(): void
 * {
 *     // 最小限のデータで作成
 *     $blogId = BlogSeeder::seed(['blog_name' => 'テストブログ']);
 *
 *     // 複数作成
 *     CategorySeeder::seed($blogId, [], 5);  // 5件のカテゴリを作成
 * }
 * ```
 *
 * トランザクションを使用してテストデータを自動的にロールバックします。
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * トランザクション開始前の自動コミット設定
     * @var bool
     */
    private $autoCommit = true;

    /**
     * テスト前の初期化処理
     *
     * データベース接続を取得し、トランザクションを開始します。
     */
    protected function setUp(): void
    {
        parent::setUp();

        try {
            // 自動コミットを無効化してトランザクションを開始
            $this->autoCommit = Database::query('SELECT @@autocommit', 'one');

            // sequenceテーブルに初期データがなければ挿入（nextvalが0を返すのを防ぐ）
            $this->ensureSequenceInitialized();

            Database::query('SET autocommit=0', 'exec');
            Database::connection()->beginTransaction();

            // テストデータのセットアップ
            $this->setUpDatabase();
        } catch (\Throwable $th) {
            self::markTestSkipped('データベース接続に失敗しました: ' . $th->getMessage());
        }
    }

    /**
     * テスト後のクリーンアップ処理
     *
     * トランザクションをロールバックし、データベースの変更を破棄します。
     */
    protected function tearDown(): void
    {
        // ロールバック
        if (Database::connection()->inTransaction()) {
            Database::connection()->rollBack();

            // 自動コミットを元に戻す
            if ($this->autoCommit) {
                Database::query('SET autocommit=1', 'exec');
            }
        }

        parent::tearDown();
    }

    /**
     * テストデータのセットアップ
     *
     * サブクラスでオーバーライドして、テストに必要なデータを準備します。
     */
    protected function setUpDatabase(): void
    {
        // サブクラスでオーバーライド可能
    }

    /**
     * sequenceテーブルに初期データがなければ挿入
     *
     * nextvalが0を返すのを防ぐため、レコードが存在しない場合は1行挿入する。
     * トランザクション開始前に実行する必要がある。
     *
     * @return void
     */
    private function ensureSequenceInitialized(): void
    {
        $subQuery = SQL::newSelect('sequence');
        $subQuery->setSelect(SQL::newField(1, null, false));
        $subQuery->setLimit(1);

        $existsSelect = SQL::newSelect('sequence');
        $existsSelect->setSelect(SQL::newOprExists($subQuery), 'exists_flag');
        $existsSelect->setLimit(1);

        if ((int) Database::query($existsSelect->get(dsn()), 'one') === 1) {
            return;
        }

        $seq = [
            'sequence_blog_id' => 0,
            'sequence_alias_id' => 0,
            'sequence_config_set_id' => 0,
            'sequence_user_id' => 0,
            'sequence_category_id' => 0,
            'sequence_entry_id' => 1,
            'sequence_comment_id' => 0,
            'sequence_rule_id' => 0,
            'sequence_module_id' => 0,
            'sequence_form_id' => 0,
            'sequence_media_id' => 0,
            'sequence_role_id' => 0,
            'sequence_usergroup_id' => 0,
            'sequence_approval_id' => 0,
            'sequence_schedule_id' => 0,
            'sequence_shop_address_id' => 0,
            'sequence_webhook_id' => 0,
            'sequence_audit_log_Id' => 0,
            'sequence_system_version' => \VERSION,
        ];
        $sql = SQL::newInsert('sequence');
        foreach ($seq as $key => $val) {
            $sql->addInsert($key, $val);
        }
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * テストデータを挿入するヘルパーメソッド
     *
     * @param string $table テーブル名
     * @param array<string, mixed> $data 挿入するデータ
     * @return int|null 挿入されたレコードのID
     */
    protected function insertTestData(string $table, array $data): ?int
    {
        $sql = SQL::newInsert($table);
        foreach ($data as $key => $value) {
            $sql->addInsert($key, $value);
        }
        Database::query($sql->get(dsn()), 'exec');

        // 最後に挿入されたIDを返す
        $id = Database::query('SELECT LAST_INSERT_ID()', 'one');
        return $id ? (int) $id : null;
    }

    /**
     * テストデータを取得するヘルパーメソッド
     *
     * @param string $table テーブル名
     * @param array<string, mixed> $where WHERE条件の配列
     * @return array<string, mixed>|null 取得したレコード
     */
    protected function fetchTestData(string $table, array $where = []): ?array
    {
        $sql = SQL::newSelect($table);
        foreach ($where as $key => $value) {
            $sql->addWhereOpr($key, $value);
        }
        $sql->setLimit(1);

        $row = Database::query($sql->get(dsn()), 'row');
        return $row === false ? null : $row;
    }

    /**
     * テストデータの件数を取得するヘルパーメソッド
     *
     * @param string $table テーブル名
     * @param array<string, mixed> $where WHERE条件の配列
     * @return int レコード数
     */
    protected function countTestData(string $table, array $where = []): int
    {
        $sql = SQL::newSelect($table);
        $sql->setSelect('*', 'count', null, 'COUNT');
        foreach ($where as $key => $value) {
            $sql->addWhereOpr($key, $value);
        }

        return (int) Database::query($sql->get(dsn()), 'one');
    }

    /**
     * テストデータを削除するヘルパーメソッド
     *
     * @param string $table テーブル名
     * @param array<string, mixed> $where WHERE条件の配列
     * @return void
     */
    protected function deleteTestData(string $table, array $where = []): void
    {
        $sql = SQL::newDelete($table);
        foreach ($where as $key => $value) {
            $sql->addWhereOpr($key, $value);
        }
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * テストブログを作成するヘルパーメソッド
     *
     * @param array<string, mixed> $data ブログデータ
     * @return int 作成されたブログID
     */
    protected function createTestBlog(array $data = []): int
    {
        $defaults = [
            'blog_name' => 'テストブログ',
            'blog_code' => 'test_' . uniqid(),
            'blog_domain' => 'test.example.com',
            'blog_status' => 'open',
            'blog_parent' => 0,
            'blog_sort' => 1,
            'blog_left' => 1,
            'blog_right' => 2,
            'blog_indexing' => 'on',
        ];

        return BlogSeeder::seed(array_merge($defaults, $data));
    }

    /**
     * テストカテゴリを作成するヘルパーメソッド
     *
     * @param int $blogId ブログID
     * @param array<string, mixed> $data カテゴリデータ
     * @return int 作成されたカテゴリID
     */
    protected function createTestCategory(int $blogId, array $data = []): int
    {
        $defaults = [
            'category_name' => 'テストカテゴリ',
            'category_code' => 'test_' . uniqid(),
            'category_status' => 'open',
            'category_scope' => 'local',
            'category_indexing' => 'on',
            'category_parent' => 0,
            'category_sort' => 1,
            'category_left' => 1,
            'category_right' => 2,
        ];

        return CategorySeeder::seed($blogId, array_merge($defaults, $data));
    }

    /**
     * テストユーザーを作成するヘルパーメソッド
     *
     * @param array<string, mixed> $data ユーザーデータ
     * @return int 作成されたユーザーID
     */
    protected function createTestUser(array $data = []): int
    {
        $defaults = [
            'user_name' => 'testuser_' . uniqid(),
            'user_pass' => acmsUserPasswordHash('password'),
            'user_mail' => 'test_' . uniqid() . '@example.com',
            'user_auth' => 'administrator',
            'user_status' => 'open',
            'user_indexing' => 'on',
        ];
        $merged = array_merge($defaults, $data);
        $blogId = (int) ($merged['user_blog_id'] ?? BID);
        unset($merged['user_blog_id']);

        return UserSeeder::seed($blogId, $merged);
    }

    /**
     * テストエントリを作成するヘルパーメソッド
     *
     * @param int $blogId ブログID
     * @param int|null $categoryId カテゴリID
     * @param array<string, mixed> $data エントリデータ
     * @return int 作成されたエントリID
     */
    protected function createTestEntry(int $blogId, ?int $categoryId, array $data = []): int
    {
        $defaults = [
            'entry_title' => 'テストエントリ',
            'entry_code' => 'test_' . uniqid(),
            'entry_status' => 'open',
            'entry_indexing' => 'on',
            'entry_sort' => 1,
            'entry_datetime' => date('Y-m-d H:i:s'),
            'entry_updated_datetime' => date('Y-m-d H:i:s'),
        ];
        $merged = array_merge($defaults, $data);
        $userId = (int) ($merged['entry_user_id'] ?? 1);
        unset($merged['entry_user_id'], $merged['entry_blog_id'], $merged['entry_category_id']);

        return EntrySeeder::seed($blogId, $userId, $categoryId, $merged);
    }
}
