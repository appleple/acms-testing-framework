<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Readme;

use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * README の「機能テスト」の例が、実際に流しているテスト（`tests/Integration/Readme/`・`tests/Unit/Readme/`）と食い違っていないことを確かめる
 *
 * README の例を直したのにテストを直さない（または逆）と、読者が写した例が動かなくなる。空白の違いは無視し、メソッド・フィールド・use 単位で照合する。
 */
final class ReadmeExamplesInSyncTest extends TestCase
{
    /**
     * README の機能テストの節の php ブロックごとの、照合する断片（メソッド・フィールド・use）の数
     * （先頭から: テストクラスの骨組み・POST の 3 例・CSRF・描画・ミドルウェアと Hook・メール・文脈の読み口・`CapturesTermination`）
     */
    private const EXPECTED_FRAGMENTS = [6, 3, 1, 1, 1, 1, 1, 5];

    private static function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    #[Test]
    #[TestDox('README の機能テストの例の、メソッド・フィールド・use は、すべて実際のテストに含まれている')]
    public function READMEの例は実際のテストに含まれる(): void
    {
        $root = dirname(__DIR__, 3);
        $readme = (string) file_get_contents($root . '/README.md');
        $tests = (string) file_get_contents($root . '/tests/Integration/Readme/FooRequestTest.php')
            . (string) file_get_contents($root . '/tests/Unit/Readme/FooHandlerTest.php');
        $start = strpos($readme, '### 機能テスト（`FeatureTestCase`）');
        $end = strpos($readme, '### 基底クラスがテストの前後で戻す状態');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $section = substr($readme, $start, $end - $start);
        preg_match_all('/```php\n(.*?)```/s', $section, $blocks);
        $this->assertCount(count(self::EXPECTED_FRAGMENTS), $blocks[1], 'README の機能テストの例（php のコードブロック）の数が変わりました');

        $normalizedTests = self::normalize($tests);
        $missing = [];
        $counts = [];
        foreach ($blocks[1] as $block) {
            if (str_starts_with(ltrim($block), 'namespace')) {
                $open = strpos($block, '{', (int) strpos($block, 'final class'));
                $close = strrpos($block, '}');
                $this->assertNotFalse($open);
                $this->assertNotFalse($close);
                $parts = preg_split('/\n\n(?=    (?:#\[Test\]|public|private|protected|use ))/', trim(substr($block, $open + 1, $close - $open - 1), "\n"));
                preg_match_all('/^use [^\n]+;$/m', $block, $uses);
                $parts = array_merge((array) $parts, $uses[0]);
            } else {
                $parts = preg_split('/\n\n(?=(?:#\[Test\]|public|private|protected))/', trim($block));
            }
            $counts[] = count((array) $parts);
            foreach ((array) $parts as $part) {
                if (!str_contains($normalizedTests, self::normalize($part))) {
                    $missing[] = mb_substr(trim($part), 0, 120);
                }
            }
        }

        // ブロックごとの断片の数を固定する。README から例を消した・足したときに、テストの照合から外れる（空の照合は通ってしまう）のを検出する。
        // 例を足す・消すときは、README と実際のテストと、ここを一緒に直す
        $this->assertSame(self::EXPECTED_FRAGMENTS, $counts, 'README の例の数が変わりました（実際のテストと、この期待値も直してください）');
        $this->assertSame([], $missing, 'README の例が、実際のテストと食い違っています（README を直したらテストも直してください）');
    }
}
