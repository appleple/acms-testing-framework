<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Seeder;

use Acms\Services\Facades\Database;
use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Seeder\BlogSeeder;
use Acms\TestingFramework\Seeder\FieldSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use SQL;

/**
 * FieldSeeder が field / field_rev へ入れる値（特に field_type の既定値と NULL の指定）の検証
 */
final class FieldSeederTest extends DatabaseTestCase
{
    private int $blogId;

    protected function setUpDatabase(): void
    {
        $this->blogId = BlogSeeder::seed(['blog_name' => 'テストブログ']);
    }

    #[Test]
    #[TestDox('field_type を指定しなければ text、値を指定すればその値で入れる')]
    public function field_typeは既定でtextになり指定した値で上書きできる(): void
    {
        FieldSeeder::seed('eid', 1, $this->blogId, 'default_type', '値');
        FieldSeeder::seed('eid', 1, $this->blogId, 'media_type', '12', ['field_type' => 'media']);

        $this->assertSame(['default_type' => 'text', 'media_type' => 'media'], $this->fieldTypes('field'));
    }

    #[Test]
    #[TestDox('field_type に null を指定すると、本番の保存経路と同じく NULL で入れる（seed / seedMany / seedRev）')]
    public function field_typeにnullを指定するとNULLで入れる(): void
    {
        FieldSeeder::seed('eid', 1, $this->blogId, 'single', '値', ['field_type' => null]);
        FieldSeeder::seedMany('eid', 1, $this->blogId, 'many', ['a', 'b'], ['field_type' => null]);
        FieldSeeder::seedRev('eid', 1, $this->blogId, 2, 'revision', '値', ['field_type' => null]);

        $this->assertSame(['many' => null, 'single' => null], $this->fieldTypes('field'));
        $this->assertSame(['revision' => null], $this->fieldTypes('field_rev'));
    }

    /**
     * @return array<string, string|null> field_key → field_type（キーの順）
     */
    private function fieldTypes(string $table): array
    {
        $sql = SQL::newSelect($table);
        $sql->addSelect('field_key');
        $sql->addSelect('field_type');
        $sql->addWhereOpr('field_blog_id', $this->blogId);
        $sql->setOrder('field_key');
        $types = [];
        foreach (Database::query($sql->get(dsn()), 'all') as $row) {
            $types[$row['field_key']] = $row['field_type'];
        }
        return $types;
    }
}
