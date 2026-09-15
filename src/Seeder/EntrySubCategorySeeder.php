<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class EntrySubCategorySeeder extends Seeder
{
    public static function seed(int $parentCategoryId, int $entryId, int $blogId = 0): void
    {
        $sql = SQL::newInsert('entry_sub_category');
        $sql->addInsert('entry_sub_category_id', $parentCategoryId);
        $sql->addInsert('entry_sub_category_eid', $entryId);
        $sql->addInsert('entry_sub_category_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }
}
