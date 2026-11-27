<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

/**
 * 機能テストが HttpKernel に流す 1 回分のリクエスト（不変）
 *
 * @internal プラグインのテストからは使わない。`FeatureTestCase::get()` / `post()` を使う
 *
 * `FeatureRequest::get('https://example.test/news/?page=2')->withBasicAuth('alice', 'secret')` のように組む。
 * `FeatureTestCase::dispatch()` が、この内容に合わせて `$_SERVER`・URL コンテキスト・環境の定数を用意する。
 */
final class FeatureRequest
{
    /**
     * @param array<string, string> $server 追加で設定する `$_SERVER` の値
     * @param array<string, string> $cookies
     * @param array<string, mixed> $post
     */
    private function __construct(
        public readonly string $method,
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $path,
        public readonly string $queryString,
        public readonly array $server = [],
        public readonly array $cookies = [],
        public readonly array $post = [],
    ) {
    }

    public static function get(string $url): self
    {
        return self::fromUrl('GET', $url);
    }

    /**
     * @param array<string, mixed> $fields POST の本文
     */
    public static function post(string $url, array $fields = []): self
    {
        $request = self::fromUrl('POST', $url);

        return new self($request->method, $request->scheme, $request->host, $request->path, $request->queryString, $request->server, $request->cookies, $fields);
    }

    private static function fromUrl(string $method, string $url): self
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException("ホストを含む URL を指定してください: {$url}");
        }
        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return new self(
            $method,
            $parts['scheme'] ?? 'http',
            $host,
            $parts['path'] ?? '/',
            $parts['query'] ?? '',
        );
    }

    public function withBasicAuth(string $user, string $password): self
    {
        return $this->withServer(['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $password]);
    }

    /**
     * @param array<string, string> $server
     */
    public function withServer(array $server): self
    {
        return new self($this->method, $this->scheme, $this->host, $this->path, $this->queryString, $server + $this->server, $this->cookies, $this->post);
    }

    /**
     * @param array<string, string> $cookies
     */
    public function withCookies(array $cookies): self
    {
        return new self($this->method, $this->scheme, $this->host, $this->path, $this->queryString, $this->server, $cookies + $this->cookies, $this->post);
    }

    /**
     * @param array<string, mixed> $post POST の本文（指定した値で置き換える）
     */
    public function withPost(array $post): self
    {
        return new self($this->method, $this->scheme, $this->host, $this->path, $this->queryString, $this->server, $this->cookies, $post);
    }

    public function isHttps(): bool
    {
        return $this->scheme === 'https';
    }

    /**
     * `REQUEST_URI`（パスとクエリ文字列）
     */
    public function requestUri(): string
    {
        return $this->path . ($this->queryString !== '' ? '?' . $this->queryString : '');
    }

    /**
     * ポートを除いたホスト名（`HTTP_HOST` 定数と同じ、小文字）
     */
    public function hostName(): string
    {
        return strtolower((string) preg_replace('/:\d+$/', '', $this->host));
    }

    /**
     * `REQUEST_URL` 定数（スキーム・ホスト・パス・クエリ文字列）
     */
    public function requestUrl(): string
    {
        return $this->scheme . '://' . $this->host . $this->requestUri();
    }

    /**
     * 先頭の `/` と末尾の `/` を除いた、ブログ・URL コンテキストを解釈するパス（`REQUEST_PATH` 定数と同じ）
     */
    public function requestPath(): string
    {
        return rtrim($this->path, '/');
    }
}
