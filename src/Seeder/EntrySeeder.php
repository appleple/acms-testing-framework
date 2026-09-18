<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class EntrySeeder extends Seeder
{
    /**
     * エントリーデータを作成
     *
     * @param int $blogId 所属するブログID
     * @param int $userId 作成者のユーザーID
     * @param int|null $categoryId カテゴリID
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成されたentry_id
     */
    public static function seed(int $blogId, int $userId, ?int $categoryId, array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('entry');

            $entryId = self::getOrFake($data, 'entry_id', $faker->unique()->numberBetween(1, 1000000));
            $now = date('Y-m-d H:i:s');

            $sql->addInsert('entry_id', $entryId);
            $sql->addInsert('entry_title', self::getOrFake($data, 'entry_title', $faker->sentence));
            $sql->addInsert('entry_code', self::getOrFake($data, 'entry_code', $faker->unique()->slug));
            $sql->addInsert('entry_status', self::getOrFake($data, 'entry_status', 'open'));
            $sql->addInsert('entry_approval', self::getOrFake($data, 'entry_approval', 'none'));
            $sql->addInsert('entry_form_status', self::getOrFake($data, 'entry_form_status', 'none'));
            $sql->addInsert('entry_form_id', self::getOrFake($data, 'entry_form_id', 0));
            $sql->addInsert('entry_sort', self::getOrFake($data, 'entry_sort', $i + 1));
            $sql->addInsert('entry_user_sort', self::getOrFake($data, 'entry_user_sort', $i + 1));
            $sql->addInsert('entry_category_sort', self::getOrFake($data, 'entry_category_sort', $i + 1));
            $sql->addInsert('entry_link', self::getOrFake($data, 'entry_link', ''));
            $sql->addInsert('entry_datetime', self::getOrFake($data, 'entry_datetime', $now));
            $sql->addInsert('entry_posted_datetime', self::getOrFake($data, 'entry_posted_datetime', $now));
            $sql->addInsert('entry_updated_datetime', self::getOrFake($data, 'entry_updated_datetime', $now));
            $sql->addInsert('entry_start_datetime', self::getOrFake($data, 'entry_start_datetime', '1000-01-01 00:00:00'));
            $sql->addInsert('entry_end_datetime', self::getOrFake($data, 'entry_end_datetime', '9999-12-31 23:59:59'));
            $sql->addInsert('entry_summary_range', self::getOrFake($data, 'entry_summary_range', null));
            $sql->addInsert('entry_indexing', self::getOrFake($data, 'entry_indexing', 'on'));
            $sql->addInsert('entry_members_only', self::getOrFake($data, 'entry_members_only', 'off'));
            $sql->addInsert('entry_primary_image', self::getOrFake($data, 'entry_primary_image', null));
            $sql->addInsert('entry_current_rev_id', self::getOrFake($data, 'entry_current_rev_id', 0));
            $sql->addInsert('entry_reserve_rev_id', self::getOrFake($data, 'entry_reserve_rev_id', 0));
            $sql->addInsert('entry_last_update_user_id', self::getOrFake($data, 'entry_last_update_user_id', $userId));
            $sql->addInsert('entry_hash', self::getOrFake($data, 'entry_hash', md5('')));
            $sql->addInsert('entry_delete_uid', self::getOrFake($data, 'entry_delete_uid', null));
            $sql->addInsert('entry_lock_datetime', self::getOrFake($data, 'entry_lock_datetime', '1000-01-01 00:00:00'));
            $sql->addInsert('entry_lock_uid', self::getOrFake($data, 'entry_lock_uid', 0));
            $sql->addInsert('entry_blog_id', $blogId);
            $sql->addInsert('entry_user_id', $userId);
            $sql->addInsert('entry_category_id', $categoryId);
            // 列挙外のカラムも $data 指定で投入する。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $entryId;
        }

        return $lastId;
    }
}
