<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * fulltext テーブル（全文検索インデックス）への Seeder。
 *
 * type は 'eid' / 'cid' / 'uid' / 'bid' のいずれかを指定する。
 */
class FulltextSeeder extends Seeder
{
    /**
     * fulltext レコードを 1 件 INSERT する。
     *
     * @param 'eid'|'cid'|'uid'|'bid' $type 紐付け先カラムのサフィックス
     * @param int $id 紐付け先 ID
     * @param int $blogId
     * @param string $value fulltext_value（LIKE モード検索対象）
     * @param string|null $ngram fulltext_ngram（BOOLEAN MODE 検索対象。省略時は $value と同じ値）
     * @return void
     */
    public static function seed(string $type, int $id, int $blogId, string $value, ?string $ngram = null): void
    {
        $sql = SQL::newInsert('fulltext');
        $sql->addInsert('fulltext_value', $value);
        $sql->addInsert('fulltext_ngram', $ngram ?? $value);
        $sql->addInsert('fulltext_' . $type, $id);
        $sql->addInsert('fulltext_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }
}
