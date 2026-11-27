<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * audit_log テーブル（監査ログ）への Seeder。
 */
class AuditLogSeeder extends Seeder
{
    /**
     * 監査ログを 1 件登録する。
     *
     * @param array<string, mixed> $data カラム名 => 値（省略したカラムは既定値・Faker で埋める）
     * @return int 登録した audit_log_id
     */
    public static function seed(array $data = []): int
    {
        $id = (int) Database::query(SQL::nextval('audit_log_id', dsn()), 'seq');

        $sql = SQL::newInsert('audit_log');
        $sql->addInsert('audit_log_id', $id);
        $sql->addInsert('audit_log_datetime', self::getOrFake($data, 'audit_log_datetime', date('Y-m-d H:i:s')));
        $sql->addInsert('audit_log_level', self::getOrFake($data, 'audit_log_level', 250));
        $sql->addInsert('audit_log_level_name', self::getOrFake($data, 'audit_log_level_name', 'NOTICE'));
        $sql->addInsert('audit_log_message', self::getOrFake($data, 'audit_log_message', self::faker()->sentence()));
        $sql->addInsert('audit_log_session_uid', self::getOrFake($data, 'audit_log_session_uid', 0));
        $sql->addInsert('audit_log_blog_id', self::getOrFake($data, 'audit_log_blog_id', 1));
        self::addExtraColumns($sql, $data);
        Database::query($sql->get(dsn()), 'exec');

        return $id;
    }
}
