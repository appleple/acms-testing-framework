<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class BlogSeeder extends Seeder
{
    /**
     * ブログデータを作成
     *
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成されたblog_id
     */
    public static function seed(array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('blog');

            $blogId = self::getOrFake($data, 'blog_id', $faker->unique()->numberBetween(1, 1000000));
            $sql->addInsert('blog_id', $blogId);
            $sql->addInsert('blog_name', self::getOrFake($data, 'blog_name', $faker->company));
            $sql->addInsert('blog_code', self::getOrFake($data, 'blog_code', $faker->unique()->slug));
            $sql->addInsert('blog_status', self::getOrFake($data, 'blog_status', 'open'));
            $sql->addInsert('blog_parent', self::getOrFake($data, 'blog_parent', 1));
            $sql->addInsert('blog_sort', self::getOrFake($data, 'blog_sort', $i + 1));
            $sql->addInsert('blog_left', self::getOrFake($data, 'blog_left', 1));
            $sql->addInsert('blog_right', self::getOrFake($data, 'blog_right', 2));
            $sql->addInsert('blog_domain', self::getOrFake($data, 'blog_domain', ''));
            $sql->addInsert('blog_indexing', self::getOrFake($data, 'blog_indexing', 'on'));
            $sql->addInsert('blog_generated_datetime', self::getOrFake($data, 'blog_generated_datetime', date('Y-m-d H:i:s')));
            $sql->addInsert('blog_maintenance_mode', self::getOrFake($data, 'blog_maintenance_mode', ''));
            // 列挙外のカラムも $data 指定で投入する。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $blogId;
        }
        return $lastId;
    }
}
