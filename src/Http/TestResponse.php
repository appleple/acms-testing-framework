<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Http;

use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * 機能テストで受け取った応答を確かめる、小さなラッパー（公開 API）
 *
 * `FeatureTestCase::get()` / `post()` の戻り値。Laravel の `TestResponse` と同じく、検証は `assert*()` のメソッドチェーンで書ける。
 * 生の PSR-7 の応答が要るときは `psr()`。
 */
final class TestResponse
{
    private const REDIRECT_STATUS = [301, 302, 303, 307, 308];

    /**
     * @param string|null $requestHost リクエストのホスト（`host` または `host:port`）。`assertRedirect()` に相対パスを渡したとき、
     *     転送先が同じホストであることの確認に使う（`FeatureTestCase` が渡す。省略したときは、絶対 URL の転送先を相対パスと照合できず失敗する）
     */
    public function __construct(private readonly ResponseInterface $response, private readonly ?string $requestHost = null)
    {
    }

    public function psr(): ResponseInterface
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    /**
     * ヘッダーの値（複数あるときは `, ` でつないだもの。無ければ空文字）
     */
    public function header(string $name): string
    {
        return $this->response->getHeaderLine($name);
    }

    public function body(): string
    {
        $body = $this->response->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }

        return (string) $body;
    }

    /**
     * 本文を JSON として読んだ値。JSON でなければ、テストを失敗させる
     *
     * @return mixed
     */
    public function json(): mixed
    {
        $decoded = json_decode($this->body(), true);
        Assert::assertSame(JSON_ERROR_NONE, json_last_error(), '応答の本文が JSON ではありません: ' . $this->body());

        return $decoded;
    }

    public function assertStatus(int $status): self
    {
        Assert::assertSame($status, $this->status(), '応答のステータスが違います。本文: ' . mb_substr($this->body(), 0, 300));

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    /**
     * 転送の応答（301 / 302 / 303 / 307 / 308）であること。`$url` を渡したら、`Location` もそれであること
     *
     * `$url` は絶対 URL か、`/` で始まるパス。
     * - 絶対 URL: `Location` と完全に一致すること（ホスト・スキームも比べる。別のホストへの転送を確かめるときはこちら）。
     * - パス: パスとクエリ文字列を比べる。`Location` が絶対 URL のときは、リクエストと同じホストへの転送であることも確かめる
     *   （パスだけの比較が、別のホスト〔外部のサイト〕への転送を通さないため。スキームは比べないので、https への転送も通る）。
     */
    public function assertRedirect(?string $url = null): self
    {
        Assert::assertContains($this->status(), self::REDIRECT_STATUS, '転送の応答ではありません（ステータス: ' . $this->status() . '）');
        if ($url !== null) {
            $location = $this->header('Location');
            if (str_starts_with($url, '/')) {
                $parts = parse_url($location);
                $parts = $parts === false ? [] : $parts;
                if (isset($parts['host'])) {
                    $locationHost = strtolower($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
                    Assert::assertSame(
                        strtolower($this->requestHost ?? ''),
                        $locationHost,
                        "転送先が別のホスト（{$locationHost}）です。別のホストへの転送を確かめるときは、絶対 URL を渡してください"
                    );
                }
                $location = ($parts['path'] ?? '') . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
            Assert::assertSame($url, $location, '転送先が違います');
        }

        return $this;
    }

    public function assertHeader(string $name, ?string $value = null): self
    {
        Assert::assertTrue($this->response->hasHeader($name), "ヘッダー {$name} がありません");
        if ($value !== null) {
            Assert::assertSame($value, $this->header($name), "ヘッダー {$name} の値が違います");
        }

        return $this;
    }

    /**
     * 本文に文字列が含まれること（HTML のエスケープはしない。そのまま探す）
     */
    public function assertSee(string $text): self
    {
        Assert::assertStringContainsString($text, $this->body());

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertStringNotContainsString($text, $this->body());

        return $this;
    }

    /**
     * 本文の JSON が、`$expected` を含むこと（連想配列は部分一致、リストは完全一致で比べる）
     *
     * 空の配列 `[]` は「リスト」として完全一致で比べる（本文が `[]` または `{}` のときだけ通る。「何かしらの JSON オブジェクトであること」は確かめられない）。
     * 特定のキーだけを確かめたいときは、そのキーを渡す。
     *
     * @param array<mixed> $expected
     */
    public function assertJson(array $expected): self
    {
        $actual = $this->json();
        Assert::assertIsArray($actual, '応答の JSON が配列ではありません');
        Assert::assertTrue(self::contains($actual, $expected), 'JSON が期待する内容を含みません。期待: ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ' 実際: ' . $this->body());

        return $this;
    }

    /**
     * @param array<mixed> $actual
     * @param array<mixed> $expected
     */
    private static function contains(array $actual, array $expected): bool
    {
        if (array_is_list($expected)) {
            return $actual === $expected;
        }
        foreach ($expected as $key => $value) {
            if (!array_key_exists($key, $actual)) {
                return false;
            }
            if (is_array($value) && !array_is_list($value)) {
                if (!is_array($actual[$key]) || !self::contains($actual[$key], $value)) {
                    return false;
                }
                continue;
            }
            if ($actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
