<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class RoleBlogSeeder extends Seeder
{
    /**
     * ロールが管轄するブログ（role_blog）を作成
     *
     * role_blog_axis が 'self' のロールは、ここで登録したブログとの完全一致で管轄が判定される。
     *
     * @param int $roleId 対象ロールID
     * @param int $blogId 管轄させるブログID
     * @return void
     */
    public static function seed(int $roleId, int $blogId): void
    {
        $sql = SQL::newInsert('role_blog');
        $sql->addInsert('role_id', $roleId);
        $sql->addInsert('blog_id', $blogId);

        Database::query($sql->get(dsn()), 'exec');
    }
}
