<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * config テーブル（ブログ・ルール・モジュール・コンフィグセット単位の設定値）への Seeder。
 *
 * 紐付け先は overrides で config_rule_id / config_module_id / config_set_id を指定する。
 * 何も指定しなければブログ全体に対するグローバル設定として登録される。
 */
class ConfigSeeder extends Seeder
{
    /**
     * config レコードを 1 件 INSERT する。
     *
     * @param int $blogId
     * @param string $key   config_key
     * @param string $value config_value
     * @param array<string, mixed> $overrides config_sort / config_rule_id / config_module_id / config_set_id の上書き値
     * @return void
     */
    public static function seed(int $blogId, string $key, string $value, array $overrides = []): void
    {
        $sql = SQL::newInsert('config');
        $sql->addInsert('config_key', $key);
        $sql->addInsert('config_value', $value);
        $sql->addInsert('config_sort', self::getOrFake($overrides, 'config_sort', 1));
        if (isset($overrides['config_rule_id'])) {
            $sql->addInsert('config_rule_id', $overrides['config_rule_id']);
        }
        if (isset($overrides['config_module_id'])) {
            $sql->addInsert('config_module_id', $overrides['config_module_id']);
        }
        if (isset($overrides['config_set_id'])) {
            $sql->addInsert('config_set_id', $overrides['config_set_id']);
        }
        $sql->addInsert('config_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }
}
