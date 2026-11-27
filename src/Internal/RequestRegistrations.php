<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

/**
 * 公開の `get()` / `post()` のリクエスト 1 回の前に控えて、リクエストの後に戻す「プラグインが足す登録」の組
 *
 * コンテナの登録（`RegistryState`）に加えて、`ACMS_Hook` の Hook の集まりと `ACMS_Namespace` の名前空間の登録を戻す。
 * 本番の `LoadPlugins` / `BootPlugins` はブログごとに有効なプラグインだけを読むため、前のリクエストが足した Hook が次のリクエストへ残ると、
 * 無効化した後・有効化していない別ブログへのリクエストでも発火してしまう。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class RequestRegistrations
{
    /**
     * @param array<string, object> $hooks
     * @param array<int, array{path: string, type: string}> $namespaces
     */
    public function __construct(
        private readonly RegistryState $registries,
        private readonly array $hooks,
        private readonly array $namespaces,
    ) {
    }

    public function restore(): void
    {
        $this->registries->restore();
        self::hookCollectionProperty()->setValue(\ACMS_Hook::singleton(), $this->hooks);
        self::namespaceProperty()->setValue(\ACMS_Namespace::singleton(), $this->namespaces);
    }

    public static function hookCollectionProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(\Acms\Services\Common\Factory::class, '_collection');
    }

    public static function namespaceProperty(): \ReflectionProperty
    {
        return new \ReflectionProperty(\ACMS_Namespace::class, '_namespaces');
    }
}
