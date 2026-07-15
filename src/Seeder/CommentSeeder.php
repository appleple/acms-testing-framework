<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * comment テーブル（エントリーへのコメント）への Seeder。
 *
 * comment はネストセットモデル（comment_left / comment_right）を保持するが、
 * テスト目的では単一レコードで十分なため初期値は (1, 2) を使う。
 */
class CommentSeeder extends Seeder
{
    /**
     * エントリーに 1 件のコメントを紐付ける。
     *
     * @param int $entryId 紐付け先 entry_id
     * @param int $blogId
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return string 登録した comment_title
     */
    public static function seed(int $entryId, int $blogId, array $data = []): string
    {
        $faker = self::faker();
        $title = self::getOrFake($data, 'comment_title', $faker->sentence);

        $sql = SQL::newInsert('comment');
        $sql->addInsert('comment_status', self::getOrFake($data, 'comment_status', 'open'));
        $sql->addInsert('comment_parent', self::getOrFake($data, 'comment_parent', 0));
        $sql->addInsert('comment_left', self::getOrFake($data, 'comment_left', 1));
        $sql->addInsert('comment_right', self::getOrFake($data, 'comment_right', 2));
        $sql->addInsert('comment_title', $title);
        $sql->addInsert('comment_body', self::getOrFake($data, 'comment_body', ''));
        $sql->addInsert('comment_name', self::getOrFake($data, 'comment_name', ''));
        $sql->addInsert('comment_mail', self::getOrFake($data, 'comment_mail', ''));
        $sql->addInsert('comment_url', self::getOrFake($data, 'comment_url', ''));
        $sql->addInsert('comment_pass', self::getOrFake($data, 'comment_pass', ''));
        $sql->addInsert('comment_datetime', self::getOrFake($data, 'comment_datetime', date('Y-m-d H:i:s')));
        $sql->addInsert('comment_host', self::getOrFake($data, 'comment_host', ''));
        $sql->addInsert('comment_rank', self::getOrFake($data, 'comment_rank', 0));
        $sql->addInsert('comment_entry_id', $entryId);
        $sql->addInsert('comment_user_id', self::getOrFake($data, 'comment_user_id', 0));
        $sql->addInsert('comment_blog_id', $blogId);
        // 列挙外のカラムも $data 指定で投入する。
        self::addExtraColumns($sql, $data);
        Database::query($sql->get(dsn()), 'exec');

        return $title;
    }
}
