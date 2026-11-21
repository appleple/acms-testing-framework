<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\StatusCode;

/**
 * ステータスをプロパティに持つ StatusCode の偽物
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeStatusCode implements StatusCode
{
    public function __construct(public string $status = '200 OK')
    {
    }

    public function get(): string
    {
        return $this->status;
    }

    public function set(string $status): void
    {
        $this->status = $status;
    }

    public function isError(): bool
    {
        return 4 <= intval(substr($this->status, 0, 1));
    }
}
