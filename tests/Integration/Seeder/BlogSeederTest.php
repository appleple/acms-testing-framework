<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Seeder;

use ACMS_RAM;
use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Seeder\BlogSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * BlogSeeder が作ったブログの行を、ACMS_RAM に残った同じ ID の古い行で読ませないことの検証。
 *
 * テストはロールバックで行を消すが、ACMS_RAM のブログの行の static なキャッシュは消えない（cacheDelete() でも残る）。
 * ブログの ID を明示して作るテストがあるため、後のテストが同じ ID で作ったブログを、前のテストの左右値で読んでしまう。
 */
final class BlogSeederTest extends DatabaseTestCase
{
    #[Test]
    #[TestDox('同じ ID のブログの行が ACMS_RAM に残っていても、作ったブログの行を読む')]
    public function seedは同じIDの古いキャッシュを読ませない(): void
    {
        $blogId = 987654321;
        ACMS_RAM::blog($blogId, ['blog_id' => $blogId, 'blog_left' => 42222, 'blog_right' => 42223]);

        BlogSeeder::seed(['blog_id' => $blogId, 'blog_left' => 9000001, 'blog_right' => 9000002]);

        $this->assertSame(9000001, (int) ACMS_RAM::blogLeft($blogId));
        $this->assertSame(9000002, (int) ACMS_RAM::blogRight($blogId));
    }

    protected function tearDown(): void
    {
        ACMS_RAM::blog(987654321, null);
        parent::tearDown();
    }
}
