<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Internal;

use Acms\Services\Cache\CacheType;
use Acms\Services\Common\InjectTemplate;
use Acms\Services\Container;
use Acms\Services\Facades\Application;
use Acms\TestingFramework\Internal\RegistryState;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(RegistryState::class)]
final class RegistryStateTest extends TestCase
{
    private ?RegistryState $baseline = null;

    protected function setUp(): void
    {
        parent::setUp();
        // このテストが足す登録（コンテナ・InjectTemplate はプロセスで共有）を、テストのあとに必ず戻す
        $this->baseline = RegistryState::capture($this->container());
    }

    protected function tearDown(): void
    {
        $this->baseline?->restore();
        parent::tearDown();
    }

    private function container(): Container
    {
        $container = \App::getInstance();
        $this->assertInstanceOf(Container::class, $container);

        return $container;
    }

    #[Test]
    #[TestDox('控えたあとに足した InjectTemplate の登録は、restore() で消え、控える前の登録は残る')]
    public function InjectTemplateの登録を戻す(): void
    {
        InjectTemplate::singleton()->add('registry-state-test', 'before.html');
        $state = RegistryState::capture($this->container());

        InjectTemplate::singleton()->add('registry-state-test', 'after.html');
        InjectTemplate::singleton()->add('registry-state-test-new', 'new.html');
        $state->restore();

        $this->assertSame(['before.html'], InjectTemplate::singleton()->get('registry-state-test'));
        $this->assertSame([], InjectTemplate::singleton()->get('registry-state-test-new'));
    }

    #[Test]
    #[TestDox('プラグインが cache.types へ足した種類は、restore() で取り除かれる')]
    public function レジストリの登録を戻す(): void
    {
        $registry = Application::make('cache.types');
        $state = RegistryState::capture($this->container());

        $registry->register(new CacheType('registry_state_test', driver: 'memory'));
        $this->assertNotNull($registry->get('registry_state_test'));
        $state->restore();

        $this->assertNull($registry->get('registry_state_test'));
        $this->assertNotNull($registry->get('page'));
        $this->assertSame($registry, Application::make('cache.types'));
    }

    #[Test]
    #[TestDox('控えたあとに初めて解決されたレジストリは、restore() で捨てられ、次は既定の登録で作り直される')]
    public function 後から解決されたレジストリは捨てる(): void
    {
        $container = $this->container();
        $container->singleton('registry-state.sample', FreshRegistry::class);
        $before = RegistryState::capture($container);
        $registry = $container->make('registry-state.sample');
        $this->assertInstanceOf(FreshRegistry::class, $registry);
        $registry->items[] = 'plugin';

        // 控えたときに未解決だったレジストリは、定義が同じでも、解決済みを捨てる
        $before->restore();

        $fresh = $container->make('registry-state.sample');
        $this->assertNotSame($registry, $fresh);
        $this->assertInstanceOf(FreshRegistry::class, $fresh);
        $this->assertSame([], $fresh->items);
    }

    #[Test]
    #[TestDox('プラグインが追加した定義・差し替え・デコレーションは、restore() で元の定義と、控えたときの解決済みのインスタンスへ戻る')]
    public function コンテナの定義を戻す(): void
    {
        $container = $this->container();
        $container->singleton('registry-state.service', static fn (): \stdClass => (object) ['name' => 'original']);
        $original = $container->make('registry-state.service');
        $state = RegistryState::capture($container);

        $container->override('registry-state.service', static fn (): \stdClass => (object) ['name' => 'plugin'], ['owner' => 'registry-state-test']);
        $container->singleton('registry-state.added', static fn (): \stdClass => (object) []);
        $this->assertSame('plugin', $container->make('registry-state.service')->name);
        $state->restore();

        $this->assertSame($original, $container->make('registry-state.service'));
        $this->assertFalse($container->exists('registry-state.added'));
    }

    #[Test]
    #[TestDox('未解決のまま控えた登録を override() で差し替えても、restore() で元のクラスで解決される（定義オブジェクトを破壊的に書き換える override() を巻き戻す）')]
    public function 未解決の登録の差し替えを戻す(): void
    {
        $container = $this->container();
        $container->singleton('registry-state.unresolved', OriginalService::class);
        $state = RegistryState::capture($container);

        $container->override('registry-state.unresolved', ReplacedService::class, ['owner' => 'registry-state-test']);
        $this->assertInstanceOf(ReplacedService::class, $container->make('registry-state.unresolved'));
        $state->restore();

        $this->assertInstanceOf(OriginalService::class, $container->make('registry-state.unresolved'));
    }

    #[Test]
    #[TestDox('同じ控えを 2 回 restore() しても、1 回目の後の差し替えが控えを汚さず、2 回とも元のクラスへ戻る')]
    public function 控えは何度戻しても汚れない(): void
    {
        $container = $this->container();
        $container->bind('registry-state.twice', OriginalService::class);
        $state = RegistryState::capture($container);

        $container->override('registry-state.twice', ReplacedService::class, ['owner' => 'first']);
        $state->restore();
        $container->override('registry-state.twice', ReplacedService::class, ['owner' => 'second']);
        $state->restore();

        $this->assertInstanceOf(OriginalService::class, $container->make('registry-state.twice'));
    }

    #[Test]
    #[TestDox('extend() で足したデコレーションは、未解決でも restore() で取り除かれる')]
    public function 未解決の登録のデコレーションを戻す(): void
    {
        $container = $this->container();
        $container->singleton('registry-state.decorated', OriginalService::class);
        $state = RegistryState::capture($container);

        $container->extend('registry-state.decorated', static fn (object $inner): object => new ReplacedService(), ['owner' => 'registry-state-test']);
        $state->restore();

        $this->assertInstanceOf(OriginalService::class, $container->make('registry-state.decorated'));
    }

    #[Test]
    #[TestDox('入れ子のオブジェクトの中身は戻さない（プロパティは代入し直すだけで、中のオブジェクトは複製しない）')]
    public function 入れ子のオブジェクトの中身は戻らない(): void
    {
        $container = $this->container();
        $container->singleton('registry-state.nested', NestedRegistry::class);
        $registry = $container->make('registry-state.nested');
        $this->assertInstanceOf(NestedRegistry::class, $registry);
        $state = RegistryState::capture($container);

        $registry->inner->items[] = 'plugin';
        $registry->list[] = 'plugin';
        $state->restore();

        $this->assertSame([], $registry->list, '配列のプロパティは戻る');
        $this->assertSame(['plugin'], $registry->inner->items, '入れ子のオブジェクトの中身は戻らない（README の約束）');
    }
}

/**
 * @internal テスト用。名前が Registry で終わるため、RegistryState の対象になる
 */
final class FreshRegistry
{
    /**
     * @var list<string>
     */
    public array $items = [];
}

/**
 * @internal テスト用
 */
final class OriginalService
{
}

/**
 * @internal テスト用
 */
final class ReplacedService
{
}

/**
 * @internal テスト用。入れ子のオブジェクトを持つ（RegistryState は中身まで戻さない）
 */
final class NestedRegistry
{
    /**
     * @var list<string>
     */
    public array $list = [];

    public \stdClass $inner;

    public function __construct()
    {
        $this->inner = (object) ['items' => []];
    }
}
