<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Helpers;

use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Helpers\PluginActivator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

final class PluginActivatorTest extends DatabaseTestCase
{
    /** プラグイン名（新形式）。 */
    private const PLUGIN = 'TestPlugin';
    /** 上記から補完される app_name（＝ ServiceProvider の FQCN。コアの get_class 相当）。 */
    private const APP_NAME = 'Acms\Plugins\TestPlugin\ServiceProvider';

    private int $testBlogId;

    protected function setUpDatabase(): void
    {
        $this->testBlogId = $this->createTestBlog();
    }

    #[Test]
    #[TestDox('プラグイン名で activate すると ServiceProvider を app_name にした行が作成され isActive が true になる')]
    public function プラグイン名でactivateするとisActiveがtrueになる(): void
    {
        PluginActivator::activate(self::PLUGIN, $this->testBlogId, '1.0.0');

        $this->assertTrue(PluginActivator::isActive(self::PLUGIN, $this->testBlogId));

        $row = $this->fetchTestData('app', [
            'app_name' => self::APP_NAME,
            'app_blog_id' => $this->testBlogId,
        ]);
        $this->assertNotNull($row);
        $this->assertSame('on', $row['app_status']);
        $this->assertSame('1.0.0', $row['app_version']);
    }

    #[Test]
    #[TestDox('既存の app 行がある場合 activate は upsert する')]
    public function 既存の行がある場合activateはupsertする(): void
    {
        PluginActivator::activate(self::PLUGIN, $this->testBlogId, '1.0.0');
        PluginActivator::activate(self::PLUGIN, $this->testBlogId, '2.0.0');

        $this->assertSame(1, $this->countTestData('app', [
            'app_name' => self::APP_NAME,
            'app_blog_id' => $this->testBlogId,
        ]));
        $this->assertTrue(PluginActivator::isActive(self::PLUGIN, $this->testBlogId));
    }

    #[Test]
    #[TestDox('deactivate すると isActive が false になる')]
    public function deactivateするとisActiveがfalseになる(): void
    {
        PluginActivator::activate(self::PLUGIN, $this->testBlogId);

        PluginActivator::deactivate(self::PLUGIN, $this->testBlogId);

        $this->assertFalse(PluginActivator::isActive(self::PLUGIN, $this->testBlogId));
    }

    #[Test]
    #[TestDox('isActive はブログ単位で判定される')]
    public function isActiveはブログ単位で判定される(): void
    {
        $otherBlogId = $this->createTestBlog();
        PluginActivator::activate(self::PLUGIN, $this->testBlogId);

        $this->assertTrue(PluginActivator::isActive(self::PLUGIN, $this->testBlogId));
        $this->assertFalse(PluginActivator::isActive(self::PLUGIN, $otherBlogId));
    }

    #[Test]
    #[TestDox('ServiceProvider の FQCN を直接渡した場合はそのまま app_name として使う')]
    public function FQCNを直接渡せる(): void
    {
        PluginActivator::activate(self::APP_NAME, $this->testBlogId);

        $this->assertTrue(PluginActivator::isActive(self::APP_NAME, $this->testBlogId));
        $this->assertNotNull($this->fetchTestData('app', [
            'app_name' => self::APP_NAME,
            'app_blog_id' => $this->testBlogId,
        ]));
    }
}
