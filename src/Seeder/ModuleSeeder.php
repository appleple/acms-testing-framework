<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class ModuleSeeder extends Seeder
{
    /**
     * モジュール行を作成する（管理画面の新規作成に近いカラム構成）。
     *
     * @param int $blogId 所属ブログ ID
     * @param array<string, mixed> $data 上書きするカラム（module_id / module_identifier 等）
     * @return int 作成された module_id
     */
    public static function seed(int $blogId, array $data = []): int
    {
        $mid = isset($data['module_id'])
            ? (int) $data['module_id']
            : (int) Database::query(SQL::nextval('module_id', dsn()), 'seq');

        $identifier = self::getOrFake($data, 'module_identifier', 'mod_' . uniqid('', true));
        $now = date('Y-m-d H:i:s');

        $sql = SQL::newInsert('module');
        $sql->addInsert('module_id', $mid);
        $sql->addInsert('module_blog_id', $blogId);
        $sql->addInsert('module_identifier', $identifier);
        $sql->addInsert('module_name', self::getOrFake($data, 'module_name', 'test_module'));
        $sql->addInsert('module_label', self::getOrFake($data, 'module_label', 'Test'));
        $sql->addInsert('module_description', self::getOrFake($data, 'module_description', ''));
        $sql->addInsert('module_status', self::getOrFake($data, 'module_status', 'open'));
        $sql->addInsert('module_scope', self::getOrFake($data, 'module_scope', 'local'));
        $sql->addInsert('module_cache', (int) self::getOrFake($data, 'module_cache', 0));
        $sql->addInsert('module_bid', self::getOrFake($data, 'module_bid', ''));
        $sql->addInsert('module_uid', self::getOrFake($data, 'module_uid', ''));
        $sql->addInsert('module_cid', self::getOrFake($data, 'module_cid', ''));
        $sql->addInsert('module_eid', self::getOrFake($data, 'module_eid', ''));
        $sql->addInsert('module_keyword', self::getOrFake($data, 'module_keyword', ''));
        $sql->addInsert('module_tag', self::getOrFake($data, 'module_tag', ''));
        $sql->addInsert('module_field', self::getOrFake($data, 'module_field', ''));
        $sql->addInsert('module_start', $data['module_start'] ?? null);
        $sql->addInsert('module_end', $data['module_end'] ?? null);
        $sql->addInsert('module_page', self::getOrFake($data, 'module_page', ''));
        $sql->addInsert('module_order', self::getOrFake($data, 'module_order', ''));
        $sql->addInsert('module_uid_scope', self::getOrFake($data, 'module_uid_scope', 'local'));
        $sql->addInsert('module_cid_scope', self::getOrFake($data, 'module_cid_scope', 'local'));
        $sql->addInsert('module_eid_scope', self::getOrFake($data, 'module_eid_scope', 'local'));
        $sql->addInsert('module_keyword_scope', self::getOrFake($data, 'module_keyword_scope', 'local'));
        $sql->addInsert('module_tag_scope', self::getOrFake($data, 'module_tag_scope', 'local'));
        $sql->addInsert('module_field_scope', self::getOrFake($data, 'module_field_scope', 'local'));
        $sql->addInsert('module_start_scope', self::getOrFake($data, 'module_start_scope', 'local'));
        $sql->addInsert('module_end_scope', self::getOrFake($data, 'module_end_scope', 'local'));
        $sql->addInsert('module_page_scope', self::getOrFake($data, 'module_page_scope', 'local'));
        $sql->addInsert('module_order_scope', self::getOrFake($data, 'module_order_scope', 'local'));
        $sql->addInsert('module_bid_axis', self::getOrFake($data, 'module_bid_axis', 'self'));
        $sql->addInsert('module_cid_axis', self::getOrFake($data, 'module_cid_axis', 'self'));
        $sql->addInsert('module_custom_field', self::getOrFake($data, 'module_custom_field', 'off'));
        $sql->addInsert('module_layout_use', (int) self::getOrFake($data, 'module_layout_use', 0));
        $sql->addInsert('module_api_use', self::getOrFake($data, 'module_api_use', 'off'));
        $sql->addInsert('module_created_datetime', self::getOrFake($data, 'module_created_datetime', $now));
        $sql->addInsert('module_updated_datetime', self::getOrFake($data, 'module_updated_datetime', $now));
        // 列挙外のカラムも $data 指定で投入する。
        self::addExtraColumns($sql, $data);

        Database::query($sql->get(dsn()), 'exec');

        return $mid;
    }
}
