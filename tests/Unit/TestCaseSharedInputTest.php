<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit;

use Acms\TestingFramework\TestCase;
use Field;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * リクエストの入力の共有の入れ物（`Field::singleton('get')` など）を、テストが書いても、テストの開始時の値へ戻す
 *
 * 書いたまま終わると、実行順で次のテストの結果が変わる（URL 生成にクエリが混ざる・保存する値に別のテストのキーが混ざる）。
 * POST の入力は、リクエストが読む `Field_Validation::singleton('post')` が別の入れ物で、`TestCasePostParameterTest` が空に戻すことを確かめる
 * （`Field::singleton('post')` はリクエストが使わないため、ここでは対象にしない）。
 */
final class TestCaseSharedInputTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (self::KEYS as $key) {
            Field::singleton($key)->deleteField('shared_input_test');
            Field::singleton($key)->removeChild('shared_input_child');
        }
        parent::tearDown();
    }

    private const KEYS = ['get', 'query', 'cookie', 'session'];

    /**
     * @return array<string, array{string}>
     */
    public static function 入れ物(): array
    {
        return array_combine(self::KEYS, array_map(static fn (string $key): array => [$key], self::KEYS));
    }

    #[Test]
    #[DataProvider('入れ物')]
    #[TestDox('テストが書いた値は、tearDown() で開始時の値へ戻る（開始時にあった値は残る）')]
    public function 書いた値を戻す(string $key): void
    {
        Field::singleton($key)->set('shared_input_test', 'before');
        $this->setUp();
        Field::singleton($key)->set('shared_input_test', 'after');
        Field::singleton($key)->set('shared_input_added', 'x');

        $this->tearDown();

        $this->assertSame('before', Field::singleton($key)->get('shared_input_test'));
        $this->assertFalse(Field::singleton($key)->isExists('shared_input_added'));
    }

    #[Test]
    #[DataProvider('入れ物')]
    #[TestDox('子の Field の中身を書き換えても、開始時の値へ戻る')]
    public function 子の中身を戻す(string $key): void
    {
        $child = new Field();
        $child->set('nested', 'before');
        Field::singleton($key)->addChild('shared_input_child', $child);
        $this->setUp();
        Field::singleton($key)->getChild('shared_input_child')->set('nested', 'after');

        $this->tearDown();

        $this->assertSame('before', Field::singleton($key)->getChild('shared_input_child')->get('nested'));
    }
}
