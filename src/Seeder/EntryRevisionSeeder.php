<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class EntryRevisionSeeder extends Seeder
{
    /**
     * エントリーリビジョンデータを作成
     *
     * @param int $entryId エントリーID
     * @param int $revisionId リビジョンID
     * @param int $blogId ブログID
     * @param int $userId ユーザーID
     * @param int $categoryId カテゴリID
     * @param array $data オーバーライドするカラム値
     * @return void
     */
    public static function seed(
        int $entryId,
        int $revisionId,
        int $blogId,
        int $userId,
        int $categoryId,
        array $data = [],
    ): void {
        $faker = self::faker();
        $now = date('Y-m-d H:i:s');

        $sql = SQL::newInsert('entry_rev');

        $sql->addInsert('entry_id', $entryId);
        $sql->addInsert('entry_rev_id', $revisionId);
        $sql->addInsert('entry_rev_status', self::getOrFake($data, 'entry_rev_status', 'draft'));
        $sql->addInsert('entry_rev_memo', self::getOrFake($data, 'entry_rev_memo', ''));
        $sql->addInsert('entry_rev_user_id', self::getOrFake($data, 'entry_rev_user_id', $userId));
        $sql->addInsert('entry_rev_datetime', self::getOrFake($data, 'entry_rev_datetime', $now));
        $sql->addInsert('entry_title', self::getOrFake($data, 'entry_title', $faker->sentence));
        $sql->addInsert('entry_code', self::getOrFake($data, 'entry_code', $faker->unique()->slug));
        $sql->addInsert('entry_status', self::getOrFake($data, 'entry_status', 'open'));
        $sql->addInsert('entry_approval', self::getOrFake($data, 'entry_approval', 'none'));
        $sql->addInsert('entry_approval_public_point', self::getOrFake($data, 'entry_approval_public_point', 0));
        $sql->addInsert('entry_approval_reject_point', self::getOrFake($data, 'entry_approval_reject_point', 0));
        $sql->addInsert('entry_form_status', self::getOrFake($data, 'entry_form_status', 'none'));
        $sql->addInsert('entry_form_id', self::getOrFake($data, 'entry_form_id', 0));
        $sql->addInsert('entry_sort', self::getOrFake($data, 'entry_sort', 1));
        $sql->addInsert('entry_user_sort', self::getOrFake($data, 'entry_user_sort', 1));
        $sql->addInsert('entry_category_sort', self::getOrFake($data, 'entry_category_sort', 1));
        $sql->addInsert('entry_link', self::getOrFake($data, 'entry_link', ''));
        $sql->addInsert('entry_datetime', self::getOrFake($data, 'entry_datetime', $now));
        $sql->addInsert('entry_start_datetime', self::getOrFake($data, 'entry_start_datetime', $now));
        $sql->addInsert('entry_end_datetime', self::getOrFake($data, 'entry_end_datetime', '9999-12-31 23:59:59'));
        $sql->addInsert('entry_posted_datetime', self::getOrFake($data, 'entry_posted_datetime', $now));
        $sql->addInsert('entry_updated_datetime', self::getOrFake($data, 'entry_updated_datetime', $now));
        $sql->addInsert('entry_indexing', self::getOrFake($data, 'entry_indexing', 'on'));
        $sql->addInsert('entry_members_only', self::getOrFake($data, 'entry_members_only', 'off'));
        $sql->addInsert('entry_primary_image', self::getOrFake($data, 'entry_primary_image', null));
        $sql->addInsert('entry_hash', self::getOrFake($data, 'entry_hash', md5('')));
        $sql->addInsert('entry_category_id', $categoryId);
        $sql->addInsert('entry_user_id', $userId);
        $sql->addInsert('entry_blog_id', $blogId);
        $sql->addInsert('entry_delete_uid', self::getOrFake($data, 'entry_delete_uid', null));
        $sql->addInsert('entry_lock_datetime', self::getOrFake($data, 'entry_lock_datetime', '1000-01-01 00:00:00'));
        $sql->addInsert('entry_lock_uid', self::getOrFake($data, 'entry_lock_uid', 0));
        // 列挙外のカラムも $data 指定で投入する。
        self::addExtraColumns($sql, $data);

        Database::query($sql->get(dsn()), 'exec');
    }
}
