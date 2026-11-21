<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Common\InjectTemplate;
use Acms\Services\Container;

/**
 * プラグインが `ServiceProvider::init()` などで足す「登録」の状態を控え、元へ戻す
 *
 * 本番は 1 リクエスト 1 プロセスなので、`init()` が足す登録（`InjectTemplate::add()`・`cache.types` / `block-editor.function-tags` /
 * `embed.providers` などのレジストリ・`Container::override()`）はプロセスと一緒に消える。機能テストは同じプロセスで `get()` を何度も流し、
 * そのたびにプラグインの `init()` が走るため、戻さないと登録がリクエストの数だけ積み上がり（`InjectTemplate::add()` は重複を除かない）、
 * テストが終わっても残って、後のテストの結果を変える。
 *
 * 戻すもの:
 * - コンテナの定義（`aliases`・`extenders`・`overrides`）。プラグインが `bind()`・`singleton()`・`override()`・`extend()` で足した・差し替えた登録。
 * - コンテナが解決済みの、名前が `Registry` で終わるサービスの状態（プロパティの値。`cache.types`・`block-editor.function-tags`・
 *   `block-editor.authoring-tags`・`embed.providers`・`background-task.registry`・`background-task.progress`・`field-definition.*`・
 *   `rest-api.*`・`scheduler.tasks`・`mcp`・`unit-registry` など）。控えたあとで初めて解決されたレジストリは、捨てて作り直させる（コアの既定の登録で始まる）。
 * - `InjectTemplate`（`Singleton` の static が持つ）。
 *
 * 戻さないもの（README に明記する）:
 * - 入れ子のオブジェクトの中身（プロパティの値は代入し直すだけで、配列の中のオブジェクトの状態までは複製しない）。
 * - `Registry` で終わらない名前のサービスの内部状態、プロセスの外の状態（ファイル・キャッシュの書き込み・メール・外部 HTTP）。
 *
 * Why not 全サービスを控える: データベース接続・ロガーなど、控えて戻すと壊れるものまで含まれる。登録のための窓口は `Registry` という名前で
 * 揃っており（`Registry` で終わらない登録先は、見つけたときに足す）、名前で選べば、コアに新しいレジストリが増えても自動で対象になる。
 * Why not 控えた状態を、プロパティごとではなくクローンで持つ: レジストリはクロージャ・ほかのサービスへの参照を持ち、`clone` では参照が共有されたまま
 * 配列だけが分かれる、という中途半端な複製になり、かえって戻らないものが分かりにくくなる。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class RegistryState
{
    /**
     * 登録先のサービスを見分ける名前の末尾
     */
    private const REGISTRY_SUFFIX = 'Registry';

    /**
     * @var array<string, mixed>
     */
    private array $aliases;

    /**
     * @var array<string, mixed>
     */
    private array $extenders;

    /**
     * @var array<string, mixed>
     */
    private array $overrides;

    /**
     * 控えた時点で解決済みだったインスタンス（エイリアス => インスタンス）
     *
     * @var array<string, mixed>
     */
    private array $resolved;

    /**
     * 控えた時点のレジストリの状態（エイリアス => プロパティの控え）
     *
     * @var array<string, list<array{class-string, string, mixed}>>
     */
    private array $registries = [];

    /**
     * @var list<array{class-string, string, mixed}>
     */
    private array $injectTemplate;

    private function __construct(private readonly Container $container)
    {
        $this->aliases = self::cloneDefinitions($this->read('aliases'));
        $this->extenders = $this->read('extenders');
        $this->overrides = $this->read('overrides');
        $this->resolved = $this->read('resolvedInstance');
        foreach ($this->resolved as $alias => $instance) {
            if (self::isRegistry($instance)) {
                $this->registries[$alias] = self::properties($instance);
            }
        }
        $this->injectTemplate = self::properties(InjectTemplate::singleton());
    }

    public static function capture(Container $container): self
    {
        return new self($container);
    }

    public function restore(): void
    {
        $changed = $this->changedAliases();
        // 控えをそのまま書き戻すと、以後の override() が控えの定義も書き換えるため、複製を書き戻す
        $this->write('aliases', self::cloneDefinitions($this->aliases));
        $this->write('extenders', $this->extenders);
        $this->write('overrides', $this->overrides);
        foreach ($changed as $alias) {
            // 定義が変わったエイリアスは、解決済みのインスタンスを控えたものへ戻す（無ければ捨てて、元の定義から作り直させる）。
            // forgetInstance() は、ファサードが持つ解決済みのインスタンスも捨てさせる
            $this->container->forgetInstance($alias);
            if (array_key_exists($alias, $this->resolved)) {
                $current = $this->read('resolvedInstance');
                $current[$alias] = $this->resolved[$alias];
                $this->write('resolvedInstance', $current);
            }
        }
        $current = $this->read('resolvedInstance');
        foreach ($this->registries as $alias => $properties) {
            if (isset($current[$alias]) && $current[$alias] === ($this->resolved[$alias] ?? null)) {
                self::apply($current[$alias], $properties);
            }
        }
        // 控えたあとで初めて解決されたレジストリ（プラグインが登録するために解決した）は、捨てて、コアの既定の登録で作り直させる
        foreach ($current as $alias => $instance) {
            if (!array_key_exists($alias, $this->resolved) && self::isRegistry($instance)) {
                $this->container->forgetInstance($alias);
            }
        }
        self::apply(InjectTemplate::singleton(), $this->injectTemplate);
    }

    /**
     * 控えたときから、定義（定義そのもの・`extend()`・`override()`）が変わったエイリアス
     *
     * @return list<string>
     */
    private function changedAliases(): array
    {
        $aliases = $this->read('aliases');
        $extenders = $this->read('extenders');
        $overrides = $this->read('overrides');
        $changed = [];
        foreach ([[self::definitionValues($this->aliases), self::definitionValues($aliases)], [$this->extenders, $extenders], [$this->overrides, $overrides]] as [$before, $after]) {
            foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $alias) {
                if (($before[$alias] ?? null) !== ($after[$alias] ?? null)) {
                    $changed[$alias] = true;
                }
            }
        }

        return array_map('strval', array_keys($changed));
    }

    /**
     * エイリアスの定義（`bind()` / `singleton()` が作る stdClass）の複製
     *
     * `Container::override()` は定義オブジェクトの `class` を破壊的に書き換える。配列だけを浅く控えると、控えも同じオブジェクトを指して
     * 一緒に書き換わり、差も検出できず元へ戻せないため、定義ごと複製する（定義の中身はクラス名・クロージャ・引数の配列で、浅い複製で足りる）。
     *
     * @param array<string, mixed> $definitions
     *
     * @return array<string, mixed>
     */
    private static function cloneDefinitions(array $definitions): array
    {
        return array_map(static fn (mixed $definition): mixed => is_object($definition) ? clone $definition : $definition, $definitions);
    }

    /**
     * 定義の比較用の値。オブジェクトの同一性ではなく、中身（種類・クラス・引数）を `===` で比べさせる
     *
     * @param array<string, mixed> $definitions
     *
     * @return array<string, mixed>
     */
    private static function definitionValues(array $definitions): array
    {
        return array_map(static fn (mixed $definition): mixed => is_object($definition) ? get_object_vars($definition) : $definition, $definitions);
    }

    private static function isRegistry(mixed $instance): bool
    {
        return is_object($instance) && str_ends_with((new \ReflectionClass($instance))->getShortName(), self::REGISTRY_SUFFIX);
    }

    /**
     * @return array<string, mixed>
     */
    private function read(string $name): array
    {
        $value = (new \ReflectionProperty(Container::class, $name))->getValue($this->container);

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $value
     */
    private function write(string $name, array $value): void
    {
        (new \ReflectionProperty(Container::class, $name))->setValue($this->container, $value);
    }

    /**
     * オブジェクトの（親クラスを含む）インスタンスのプロパティの値。読み取り専用・未初期化のものは対象外
     *
     * @return list<array{class-string, string, mixed}>
     */
    private static function properties(object $object): array
    {
        $state = [];
        for ($class = new \ReflectionClass($object); $class !== false; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || $property->isReadOnly() || $property->getDeclaringClass()->getName() !== $class->getName() || !$property->isInitialized($object)) {
                    continue;
                }
                $state[] = [$class->getName(), $property->getName(), $property->getValue($object)];
            }
        }

        return $state;
    }

    /**
     * @param list<array{class-string, string, mixed}> $properties
     */
    private static function apply(object $object, array $properties): void
    {
        foreach ($properties as [$class, $name, $value]) {
            (new \ReflectionProperty($class, $name))->setValue($object, $value);
        }
    }
}
