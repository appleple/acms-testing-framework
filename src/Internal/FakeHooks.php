<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\Hooks;

/**
 * 発火を記録し、名前ごとに登録したクロージャで引数を書き換えられる Hooks の偽物
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeHooks implements Hooks
{
    /**
     * 発火した Hook の名前と、そのときの引数（値のコピー）
     *
     * @var list<array{string, array<int, mixed>}>
     */
    public array $calls = [];

    /**
     * Hook が有効か（本番の `HOOK_ENABLE` にあたる）。false にしても発火は記録する
     */
    public bool $enabled = true;

    /**
     * @var array<string, \Closure>
     */
    private array $handlers = [];

    /**
     * Hook の名前にクロージャを登録する。引数の配列は参照のまま渡すので、`function (array &$arguments)` で書き換えられる
     *
     * @param \Closure(array<int, mixed>): void $handler
     */
    public function on(string $name, \Closure $handler): void
    {
        $this->handlers[$name] = $handler;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function call(string $name, array $arguments = []): void
    {
        $this->calls[] = [$name, $arguments];
        if (isset($this->handlers[$name])) {
            ($this->handlers[$name])($arguments);
        }
    }

    /**
     * @return list<string> 発火した Hook の名前（順序どおり）
     */
    public function names(): array
    {
        return array_map(static fn (array $call): string => $call[0], $this->calls);
    }
}
