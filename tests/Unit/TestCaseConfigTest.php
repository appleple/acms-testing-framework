<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use Field;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * テストが書き換えたコンフィグを、テストの開始時の値へ戻す（子の Field の中身も含む）
 */
final class TestCaseConfigTest extends TestCase
{
    private ?Field $addedChild = null;

    protected function tearDown(): void
    {
        $config = Field::singleton('config');
        $config->removeChild('testcase_config_child');
        $config->deleteField('testcase_config_value');
        $this->addedChild = null;
        parent::tearDown();
    }

    private function seedConfig(): Field
    {
        $config = Field::singleton('config');
        $child = new Field();
        $child->set('nested', 'before');
        $config->addChild('testcase_config_child', $child);
        $config->set('testcase_config_value', 'before');
        $this->addedChild = $child;

        // 開始時の値として控え直す
        $this->setUp();

        return $config;
    }

    #[Test]
    #[TestDox('tearDown() が、テストが書き換えたトップレベルの値を開始時の値へ戻す')]
    public function tearDownRestoresTopLevelValue(): void
    {
        $config = $this->seedConfig();
        $config->set('testcase_config_value', 'after');

        $this->tearDown();

        $this->assertSame('before', Field::singleton('config')->get('testcase_config_value'));
    }

    #[Test]
    #[TestDox('tearDown() が、子の Field の中身を書き換えても開始時の値へ戻す')]
    public function tearDownRestoresChildFieldContents(): void
    {
        $config = $this->seedConfig();
        $config->getChild('testcase_config_child')->set('nested', 'after');
        $config->getChild('testcase_config_child')->set('added', 'x');

        $this->tearDown();

        $child = Field::singleton('config')->getChild('testcase_config_child');
        $this->assertSame('before', $child->get('nested'));
        $this->assertFalse($child->isExists('added'));
    }
}
