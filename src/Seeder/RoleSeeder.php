<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class RoleSeeder extends Seeder
{
    /**
     * ロールデータを作成
     *
     * 権限フラグ（role_form_edit など）は既定で 'off'。付与したい権限を $data で 'on' に上書きする。
     * 管轄ブログは role_blog テーブルで表すため RoleBlogSeeder と併用する。
     *
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成された role_id
     */
    public static function seed(array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        $actionColumns = [
            'role_entry_edit_all',
            'role_entry_edit',
            'role_entry_delete',
            'role_category_create',
            'role_category_edit',
            'role_form_view',
            'role_form_edit',
            'role_tag_edit',
            'role_media_upload',
            'role_media_edit',
            'role_rule_edit',
            'role_publish_edit',
            'role_publish_exec',
            'role_config_edit',
            'role_module_edit',
            'role_backup_export',
            'role_backup_import',
            'role_admin_etc',
        ];

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('role');

            $roleId = self::getOrFake($data, 'role_id', $faker->unique()->numberBetween(1, 1000000));
            $sql->addInsert('role_id', $roleId);
            $sql->addInsert('role_name', self::getOrFake($data, 'role_name', $faker->word));
            $sql->addInsert('role_description', self::getOrFake($data, 'role_description', ''));
            $sql->addInsert('role_blog_axis', self::getOrFake($data, 'role_blog_axis', 'self'));
            foreach ($actionColumns as $column) {
                $sql->addInsert($column, self::getOrFake($data, $column, 'off'));
            }
            // 列挙外のカラムも $data 指定で投入する。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $roleId;
        }

        return $lastId;
    }
}
