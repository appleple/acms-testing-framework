<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Fixtures\FeaturePlugin;

use Acms\Contracts\ServiceProvider as BaseServiceProvider;
use Acms\Services\Common\InjectTemplate;
use Acms\Services\Container;
use Acms\Services\Facades\Application;

/**
 * FeatureTestCase の機能テストが有効化して確かめる、プラグインに見立てたサービスプロバイダー
 *
 * プラグインと同じく、`earlyInit()` でミドルウェアを `http.middleware` へ登録し、`init()` で Hook と `InjectTemplate` への差し込みを登録する。
 * 名前空間は `Acms\Plugins\...` ではないため、有効化では FQCN を渡す（`PluginActivator::activate(ServiceProvider::class, ...)`）。
 */
final class ServiceProvider extends BaseServiceProvider
{
    /**
     * `init()` が `InjectTemplate` へ登録する種類（機能テストが、登録の積み上がりを確かめる）
     */
    public const INJECT_TYPE = 'feature-fixture';

    public function register(Container $container): void
    {
    }

    public function earlyInit(): void
    {
        $registry = Application::make('http.middleware');
        assert($registry instanceof \Acms\Services\Http\Kernel\Extension\StageMiddlewareRegistry);
        $registry->after('session.restore', new MarkerMiddleware(), ['owner' => 'feature-fixture-plugin']);
    }

    public function init(): void
    {
        \ACMS_Hook::singleton()->attach('FeatureFixturePlugin', new Hook());
        // 重複を除かない登録。リクエストごとに足すと、戻さなければ get() の数だけ積み上がる
        InjectTemplate::singleton()->add(self::INJECT_TYPE, 'feature-fixture.html');
        // 定義オブジェクトを破壊的に書き換える登録。リクエストの後に戻さないと、後のテストの markdown が差し替わったままになる
        Application::getInstance()->override('markdown', MarkerMarkdownConverter::class, ['owner' => 'feature-fixture-plugin']);
    }
}
