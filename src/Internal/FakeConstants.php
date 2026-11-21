<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\Constants;

/**
 * 定数を配列に記録する Constants の偽物
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeConstants implements Constants
{
    /**
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * define() の呼び出しの記録（同じ名前を 2 回定義したことも残る）
     *
     * @var list<array{string, mixed}>
     */
    public array $defined = [];

    /**
     * @param array<string, mixed> $values 最初から定義済みの定数
     * @param bool $ignoresRedefinition true なら、定義済みの定数への define() を本番の `define()` と同じく無視する（値は先のまま。呼び出しの記録には残る）。
     *     既定の false は、あとの値で上書きする（既存のテストが、定義済みの既定値を上書きして確かめているため）
     */
    public function __construct(array $values = [], private readonly bool $ignoresRedefinition = false)
    {
        $this->values = $values;
    }

    public function define(string $name, mixed $value): void
    {
        $this->defined[] = [$name, $value];
        if ($this->ignoresRedefinition && $this->isDefined($name)) {
            return;
        }
        $this->values[$name] = $value;
    }

    public function defineIfUndefined(string $name, mixed $value): void
    {
        if (!$this->isDefined($name)) {
            $this->define($name, $value);
        }
    }

    public function isDefined(string $name): bool
    {
        return array_key_exists($name, $this->values);
    }

    public function value(string $name): mixed
    {
        if (!$this->isDefined($name)) {
            throw new \LogicException("定数 {$name} が定義されていません");
        }
        return $this->values[$name];
    }

    /**
     * define() で定義された回数
     */
    public function definedCount(string $name): int
    {
        return count(array_filter($this->defined, static fn (array $entry): bool => $entry[0] === $name));
    }
}
