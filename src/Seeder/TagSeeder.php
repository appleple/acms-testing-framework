<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * tag テーブル（エントリーに紐付くタグ）への Seeder。
 *
 * tag テーブルは tag_id 列を持たず (tag_sort, tag_entry_id) の複合主キーで識別される。
 */
class TagSeeder extends Seeder
{
    /**
     * エントリーに 1 件のタグを紐付ける。
     *
     * @param int $entryId 紐付け先 entry_id
     * @param int $blogId
     * @param string $tagName
     * @param int $tagSort
     * @return string 登録した tag_name
     */
    public static function seed(int $entryId, int $blogId, string $tagName, int $tagSort = 1): string
    {
        $sql = SQL::newInsert('tag');
        $sql->addInsert('tag_name', $tagName);
        $sql->addInsert('tag_sort', $tagSort);
        $sql->addInsert('tag_entry_id', $entryId);
        $sql->addInsert('tag_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
        return $tagName;
    }

    /**
     * エントリーに複数のタグを順番付けて紐付ける。
     *
     * @param int $entryId
     * @param int $blogId
     * @param list<string> $tagNames
     * @return list<string>
     */
    public static function seedMany(int $entryId, int $blogId, array $tagNames): array
    {
        foreach ($tagNames as $i => $name) {
            self::seed($entryId, $blogId, $name, $i + 1);
        }
        return $tagNames;
    }
}
