<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Kernel\Runtime\GlobalResponses;
use Acms\Services\Http\Kernel\Runtime\Hooks;
use Acms\Services\Http\Kernel\Runtime\Runtime;
use Acms\Services\RestApi\Http\MessageFactory;

/**
 * 窓口の偽物を詰めた Runtime と、その偽物への参照
 *
 * 使い方: `$fake = FakeRuntime::create(['HTTP_HOST' => 'example.com']);`
 * `$fake->runtime` をミドルウェアへ渡し、`$fake->constants` などで結果を確かめる。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class FakeRuntime
{
    private function __construct(
        public readonly Runtime $runtime,
        public readonly FakeConstants $constants,
        public readonly FakeStatusCode $status,
        public readonly FakeHooks $hooks,
        public readonly GlobalResponses $responses,
        public readonly FakeHeaders $headers,
        public readonly FakeOutput $output,
    ) {
    }

    /**
     * @param array<string, mixed> $constants 最初から定義済みの定数
     * @param FakeConstants|null $sharedConstants 渡すと、新しい定数の偽物を作らずこれを使う（RequestState の文脈と定数を共有するため。
     *     `$constants` は、この偽物へ定義済みとして足す）
     * @param Hooks|null $runtimeHooks 渡すと、Runtime の Hook の窓口にこれを使う（`GlobalHooks` を渡すと、プラグインの Hook が本当に発火する）。
     *     そのとき `$hooks`（FakeHooks）は何も記録しない空のまま
     */
    public static function create(array $constants = [], ?FakeConstants $sharedConstants = null, ?Hooks $runtimeHooks = null): self
    {
        $fakeConstants = $sharedConstants ?? new FakeConstants();
        foreach ($constants as $name => $value) {
            $fakeConstants->values[$name] = $value;
        }
        $status = new FakeStatusCode();
        $hooks = new FakeHooks();
        // 終了の応答は偽物にせず本番の実装を使う（応答を返すだけで、送出も終了もしないため）
        $responses = new GlobalResponses(new MessageFactory());
        $headers = new FakeHeaders();
        $output = new FakeOutput();

        return new self(
            new Runtime($fakeConstants, $status, $runtimeHooks ?? $hooks, $responses, $headers, $output),
            $fakeConstants,
            $status,
            $hooks,
            $responses,
            $headers,
            $output,
        );
    }
}
