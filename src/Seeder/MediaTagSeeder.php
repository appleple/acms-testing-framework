<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * media_tag テーブル（メディアに紐付くタグ）への Seeder。
 */
class MediaTagSeeder extends Seeder
{
    /**
     * メディアに 1 件のタグを紐付ける。
     *
     * @param int $mediaId 紐付け先 media_id
     * @param int $blogId
     * @param string $tagName
     * @param int $tagSort
     * @return string
     */
    public static function seed(int $mediaId, int $blogId, string $tagName, int $tagSort = 1): string
    {
        $sql = SQL::newInsert('media_tag');
        $sql->addInsert('media_tag_name', $tagName);
        $sql->addInsert('media_tag_sort', $tagSort);
        $sql->addInsert('media_tag_media_id', $mediaId);
        $sql->addInsert('media_tag_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
        return $tagName;
    }
}
