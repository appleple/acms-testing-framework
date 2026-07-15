<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Seeder;

use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Seeder\BlogSeeder;
use Acms\TestingFramework\Seeder\CategorySeeder;
use Acms\TestingFramework\Seeder\EntrySeeder;
use Acms\TestingFramework\Seeder\UserSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * 各 Seeder への「任意カラム対応」（Seeder::addExtraColumns）の横展開を確認する特徴づけテスト。
 *
 * UserSeeder 個別の検証は UserSeederTest が担う。本テストは共有ヘルパーが代表的な Seeder
 * （引数 FK なしの Blog / 引数 FK ありの Entry）にも効いていることをスポットで固定する。
 */
final class SeederExtraColumnsTest extends DatabaseTestCase
{
    #[Test]
    #[TestDox('BlogSeeder: 既定値を列挙していないカラム（blog_alias_status）を $data で投入できる')]
    public function blogSeederPersistsExtraColumn(): void
    {
        $blogId = BlogSeeder::seed(['blog_alias_status' => 'close']);

        $row = $this->fetchTestData('blog', ['blog_id' => $blogId]);
        $this->assertNotNull($row);
        $this->assertSame('close', $row['blog_alias_status']);
    }

    #[Test]
    #[TestDox('EntrySeeder: FK 引数（entry_blog_id）は $data の同名指定より優先される')]
    public function entrySeederKeepsForeignKeyArgumentOverData(): void
    {
        $blogId = BlogSeeder::seed([]);
        $userId = UserSeeder::seed($blogId, []);
        $categoryId = CategorySeeder::seed($blogId, []);

        $entryId = EntrySeeder::seed($blogId, $userId, $categoryId, [
            'entry_blog_id' => $blogId + 999, // 引数が正。addExtraColumns で無視される。
        ]);

        $row = $this->fetchTestData('entry', ['entry_id' => $entryId]);
        $this->assertNotNull($row);
        $this->assertSame($blogId, (int) $row['entry_blog_id']);
    }
}
