<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\Headers;

/**
 * ヘッダーの送出を記録する Headers の偽物
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeHeaders implements Headers
{
    /**
     * header() の呼び出し（ヘッダー行と replace）
     *
     * @var list<array{string, bool}>
     */
    public array $sent = [];

    /**
     * safeHeaders() の呼び出し（ステータスコードと MIME）。終了の応答は、ステータス行を send() ではなくここへ記録する
     *
     * @var list<array{int, string}>
     */
    public array $safeHeaders = [];

    public int $securityHeaderCount = 0;

    /**
     * clientCacheHeaders()（その場で送る）の呼び出し（noCache の値）。応答には載せず、送出の記録で確かめる
     *
     * @var list<bool>
     */
    public array $clientCacheSent = [];

    /**
     * send()・safeHeaders()・securityHeaders()・clientCacheHeaders() の呼び出しを、呼ばれた順に並べたもの
     * （`send: {行}`・`safe: {コード} {MIME}`・`security`・`cache: no-cache` / `cache: cacheable`）。safeHeaders() は本番と同じく、
     * 続けて Content-type の送出・セキュリティヘッダー・キャッシュ無効のヘッダーも記録する。送出の順序を確かめるために使う
     *
     * @var list<string>
     */
    public array $events = [];

    /**
     * sent() が返す値（出力済みのとき true）
     */
    public bool $headersSent = false;

    public function send(string $header, bool $replace = true): void
    {
        $this->sent[] = [$header, $replace];
        $this->events[] = 'send: ' . $header;
    }

    public function sent(): bool
    {
        return $this->headersSent;
    }

    public function safeHeaders(int $code = 200, string $mime = 'text/html'): void
    {
        $this->safeHeaders[] = [$code, $mime];
        $this->events[] = 'safe: ' . $code . ' ' . $mime;
        // 本番（Response::safeHeaders）の中身: http_response_code() → Content-type → addSecurityHeader() → clientCacheHeader(true)
        $this->send("Content-type: {$mime}; charset=" . config('charset', 'UTF-8'));
        $this->securityHeaders();
        $this->clientCacheHeaders(true);
    }

    public function securityHeaders(): void
    {
        $this->securityHeaderCount++;
        $this->events[] = 'security';
    }

    public function clientCacheHeaders(bool $noCache): void
    {
        $this->clientCacheSent[] = $noCache;
        $this->events[] = $noCache ? 'cache: no-cache' : 'cache: cacheable';
    }
}
