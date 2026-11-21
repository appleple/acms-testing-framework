<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\Output;

/**
 * 出力した本文を記録する Output の偽物（`printContents()` を通した本文は `printed`、`echo` した本文は `written`）
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeOutput implements Output
{
    /**
     * @var list<string>
     */
    public array $printed = [];

    /**
     * write()（`echo`）で出力した本文
     *
     * @var list<string>
     */
    public array $written = [];

    public function print(string $contents): void
    {
        $this->printed[] = $contents;
    }

    public function write(string $contents): void
    {
        $this->written[] = $contents;
    }
}
