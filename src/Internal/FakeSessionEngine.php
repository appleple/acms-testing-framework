<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Session\Engine;

/**
 * PHP のネイティブセッションを開かず、値をメモリに持つセッションの偽物（`Session` ファサードの差し替え先）
 *
 * 本物の Engine はコンストラクターで `session_start()` を呼ぶ。機能テストは CLI で動く（ヘッダー送出済みの状態で、Cookie も使えない）ため、
 * 開けないし、開くとテスト間でセッションが残る。ログインの復元（`RestoreSession`）と CSRF トークン（`CsrfTokenManager`）が読む
 * `get()` / `set()` と、ログアウトが呼ぶ `destroy()` だけを、同じ意味でメモリ上に実装する。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeSessionEngine extends Engine
{
    private bool $destroyed = false;

    /**
     * @param string $sessionId Cookie に載せるセッション ID
     * @param array<string, mixed> $storage 最初に持たせる値
     */
    // @phpstan-ignore constructor.missingParentCall (親の Engine は session_start() を伴う初期化をするため、メモリ上の偽物では呼ばない)
    public function __construct(private readonly string $sessionId, array $storage = [])
    {
        $this->storage = $storage;
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    public function isDestroyed(): bool
    {
        return $this->destroyed;
    }

    public function writeClose(): void
    {
    }

    public function regenerate(): void
    {
    }

    public function save(): void
    {
    }

    public function clear(): void
    {
        $this->storage = [];
    }

    public function destroy(): void
    {
        $this->storage = [];
        $this->destroyed = true;
    }

    public function extendExpires(): void
    {
    }

    protected function start(): void
    {
    }
}
