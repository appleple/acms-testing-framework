<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Asset\AssetServiceProvider;
use Acms\Services\Facades\Asset;

/**
 * `asset` サービスの登録を、本番と同じ（リクエストごとに作り直せる singleton）へ戻す
 *
 * `Asset::swap($original)` で元の実体へ戻すと、登録が「その実体を返し続ける定義」になり、`Container::forgetInstance('asset')`
 * （`Application::resetRequestState()`・機能テストの `GlobalStateGuard`）で捨てても同じ実体が返る。登録したアセットのハンドルが
 * 後のテストへ残り、実行順によって失敗する。差し替えたテストの後始末は、実体ではなく登録そのものを戻す。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class AssetBinding
{
    public static function restore(): void
    {
        (new AssetServiceProvider())->register(\App::getInstance());
        Asset::clearInstance();
    }
}
