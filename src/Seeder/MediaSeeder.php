<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class MediaSeeder extends Seeder
{
    /**
     * メディアデータを作成
     *
     * @param int $blogId 所属するブログID
     * @param int $userId 作成者のユーザーID
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return int 作成された media_id
     */
    public static function seed(int $blogId, int $userId = 1, array $data = []): int
    {
        $faker = self::faker();
        $sql = SQL::newInsert('media');
        $now = date('Y-m-d H:i:s');

        $mid = self::getOrFake($data, 'media_id', $faker->unique()->numberBetween(1, 1000000));
        $sql->addInsert('media_id', $mid);
        $sql->addInsert('media_status', self::getOrFake($data, 'media_status', 'open'));
        $sql->addInsert('media_type', self::getOrFake($data, 'media_type', 'file'));
        $sql->addInsert('media_extension', self::getOrFake($data, 'media_extension', 'pdf'));
        $sql->addInsert('media_path', self::getOrFake($data, 'media_path', 'test/' . $faker->uuid . '.pdf'));
        $sql->addInsert('media_thumbnail', self::getOrFake($data, 'media_thumbnail', ''));
        $sql->addInsert('media_original', self::getOrFake($data, 'media_original', ''));
        $sql->addInsert('media_file_name', self::getOrFake($data, 'media_file_name', 'test.pdf'));
        $sql->addInsert('media_image_size', self::getOrFake($data, 'media_image_size', 0));
        $sql->addInsert('media_file_size', self::getOrFake($data, 'media_file_size', 0));
        $sql->addInsert('media_upload_date', self::getOrFake($data, 'media_upload_date', $now));
        $sql->addInsert('media_update_date', self::getOrFake($data, 'media_update_date', $now));
        $sql->addInsert('media_field_1', self::getOrFake($data, 'media_field_1', ''));
        $sql->addInsert('media_field_2', self::getOrFake($data, 'media_field_2', ''));
        $sql->addInsert('media_field_3', self::getOrFake($data, 'media_field_3', ''));
        $sql->addInsert('media_field_4', self::getOrFake($data, 'media_field_4', ''));
        $sql->addInsert('media_field_5', self::getOrFake($data, 'media_field_5', ''));
        $sql->addInsert('media_field_6', self::getOrFake($data, 'media_field_6', ''));
        $sql->addInsert('media_user_id', self::getOrFake($data, 'media_user_id', $userId));
        $sql->addInsert('media_blog_id', self::getOrFake($data, 'media_blog_id', $blogId));
        // 列挙外のカラムも $data 指定で投入する。
        self::addExtraColumns($sql, $data);

        Database::query($sql->get(dsn()), 'exec');
        return $mid;
    }
}
