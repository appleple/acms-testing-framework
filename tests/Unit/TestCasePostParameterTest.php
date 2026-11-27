<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use Field_Validation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * 前のテストが POST の共有の入れ物（`Field_Validation::singleton('post')`）へ書いた値・検証の指定を、次のテストへ持ち越さない
 *
 * キーの消し忘れや検証の指定（required など）の残りで、実行順によって無関係なテストの検証が失敗していた。
 */
final class TestCasePostParameterTest extends TestCase
{
    protected function tearDown(): void
    {
        $post = Field_Validation::singleton('post');
        [$post->_aryField, $post->_aryChild, $post->_aryMeta, $post->_aryV, $post->_aryMethod, $post->_aryGroup] = [[], [], [], [], [], []];
        parent::tearDown();
    }

    private function dirtyPost(): Field_Validation
    {
        $post = Field_Validation::singleton('post');
        $post->set('leftover', 'value');
        $post->setMethod('leftover', 'required');
        $post->validate();

        return $post;
    }

    #[Test]
    #[TestDox('setUp() が、前のテストが残した値と検証の指定を消す')]
    public function setUpClearsPost(): void
    {
        $post = $this->dirtyPost();
        $this->assertSame('value', $post->get('leftover'));

        $this->setUp();

        $this->assertSame('', $post->get('leftover'));
        $this->assertSame([], $post->_aryMethod);
        $this->assertSame([], $post->_aryV);
        $this->assertTrue($post->isValidAll());
    }

    #[Test]
    #[TestDox('tearDown() が、このテストが書いた値と検証の指定を消す')]
    public function tearDownClearsPost(): void
    {
        $post = $this->dirtyPost();

        $this->tearDown();

        $this->assertSame('', $post->get('leftover'));
        $this->assertSame([], $post->_aryMethod);
    }
}
