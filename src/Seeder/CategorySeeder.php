<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class CategorySeeder extends Seeder
{
    /**
     * カテゴリデータを作成
     *
     * @param int $blogId 所属するブログID
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成されたcategory_id
     */
    public static function seed(int $blogId, array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('category');

            $categoryId = self::getOrFake($data, 'category_id', $faker->unique()->numberBetween(1, 1000000));
            $sql->addInsert('category_id', $categoryId);
            $sql->addInsert('category_name', self::getOrFake($data, 'category_name', $faker->word));
            $sql->addInsert('category_code', self::getOrFake($data, 'category_code', $faker->unique()->slug));
            $sql->addInsert('category_status', self::getOrFake($data, 'category_status', 'open'));
            $sql->addInsert('category_parent', self::getOrFake($data, 'category_parent', 0));
            $sql->addInsert('category_sort', self::getOrFake($data, 'category_sort', $i + 1));
            $sql->addInsert('category_left', self::getOrFake($data, 'category_left', 1));
            $sql->addInsert('category_right', self::getOrFake($data, 'category_right', 2));
            $sql->addInsert('category_indexing', self::getOrFake($data, 'category_indexing', 'on'));
            $sql->addInsert('category_blog_id', $blogId);
            // 列挙外のカラムも $data 指定で投入する。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $categoryId;
        }

        return $lastId;
    }
}
