<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * approval テーブル（承認フローの依頼・承認・コメントの履歴）への Seeder。
 */
class ApprovalSeeder extends Seeder
{
    /**
     * 承認フローの履歴を 1 件登録する。
     *
     * @param int $entryId エントリーID
     * @param int $revisionId リビジョンID
     * @param int $blogId ブログID
     * @param array<string, mixed> $data カラム名 => 値（省略したカラムは既定値・Faker で埋める）
     * @return int 登録した approval_id
     */
    public static function seed(int $entryId, int $revisionId, int $blogId, array $data = []): int
    {
        $id = (int) Database::query(SQL::nextval('approval_id', dsn()), 'seq');

        $sql = SQL::newInsert('approval');
        $sql->addInsert('approval_id', $id);
        $sql->addInsert('approval_type', self::getOrFake($data, 'approval_type', 'request'));
        $sql->addInsert('approval_method', self::getOrFake($data, 'approval_method', 'series'));
        $sql->addInsert('approval_datetime', self::getOrFake($data, 'approval_datetime', date('Y-m-d H:i:s')));
        $sql->addInsert('approval_deadline_datetime', self::getOrFake($data, 'approval_deadline_datetime', '9999-12-31 23:59:59'));
        $sql->addInsert('approval_comment', self::getOrFake($data, 'approval_comment', self::faker()->sentence()));
        $sql->addInsert('approval_revision_id', $revisionId);
        $sql->addInsert('approval_entry_id', $entryId);
        $sql->addInsert('approval_blog_id', $blogId);
        self::addExtraColumns($sql, $data);
        Database::query($sql->get(dsn()), 'exec');

        return $id;
    }
}
