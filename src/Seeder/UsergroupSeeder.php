<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class UsergroupSeeder extends Seeder
{
    /**
     * ユーザーグループデータを作成
     *
     * @param int $roleId 紐づけるロールID（usergroup_role_id）
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成された usergroup_id
     */
    public static function seed(int $roleId, array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('usergroup');

            $usergroupId = self::getOrFake($data, 'usergroup_id', $faker->unique()->numberBetween(1, 1000000));
            $sql->addInsert('usergroup_id', $usergroupId);
            $sql->addInsert('usergroup_name', self::getOrFake($data, 'usergroup_name', $faker->word));
            $sql->addInsert('usergroup_description', self::getOrFake($data, 'usergroup_description', ''));
            $sql->addInsert('usergroup_approval_point', self::getOrFake($data, 'usergroup_approval_point', 0));
            $sql->addInsert('usergroup_role_id', $roleId);
            // 列挙外のカラムも $data 指定で投入する。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $usergroupId;
        }

        return $lastId;
    }
}
