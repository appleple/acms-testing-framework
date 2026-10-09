<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class UsergroupUserSeeder extends Seeder
{
    /**
     * ユーザーグループへのユーザー所属（usergroup_user）を作成
     *
     * @param int $usergroupId 対象ユーザーグループID
     * @param int $userId 所属させるユーザーID
     * @return void
     */
    public static function seed(int $usergroupId, int $userId): void
    {
        $sql = SQL::newInsert('usergroup_user');
        $sql->addInsert('usergroup_id', $usergroupId);
        $sql->addInsert('user_id', $userId);

        Database::query($sql->get(dsn()), 'exec');
    }
}
