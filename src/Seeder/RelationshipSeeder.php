<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * relationship テーブル（エントリー間の関連付け）への Seeder。
 *
 * relationship テーブルは (relation_id, relation_eid, relation_type) の複合主キーで識別される。
 * - relation_id : 主体側のエントリーID（このエントリーから見て関連先を保持する）
 * - relation_eid: 関連先のエントリーID
 * - relation_type: 関連タイプ（'default' など）
 */
class RelationshipSeeder extends Seeder
{
    /**
     * 関連エントリーを 1 件 INSERT する。
     *
     * @param int $entryId 主体側のエントリーID
     * @param int $relatedEntryId 関連先のエントリーID
     * @param string $type 関連タイプ（デフォルト: 'default'）
     * @param int $order 並び順（デフォルト: 1）
     * @return void
     */
    public static function seed(int $entryId, int $relatedEntryId, string $type = 'default', int $order = 1): void
    {
        $sql = SQL::newInsert('relationship');
        $sql->addInsert('relation_id', $entryId);
        $sql->addInsert('relation_eid', $relatedEntryId);
        $sql->addInsert('relation_type', $type);
        $sql->addInsert('relation_order', $order);
        Database::query($sql->get(dsn()), 'exec');
    }
}
