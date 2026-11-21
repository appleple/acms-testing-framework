<?php

declare(strict_types=1);

namespace Acms\Plugins\Foo;

use Acms\Contracts\ServiceProvider as BaseServiceProvider;
use Acms\Services\Container;
use Acms\Services\Facades\Application;
use Acms\Services\Http\Context;
use Acms\Services\Http\Kernel\Extension\StageMiddlewareRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * README の機能テストの例が有効化する、プラグイン Foo のサービスプロバイダー（`Acms\Plugins\Foo\ServiceProvider`）
 */
final class ServiceProvider extends BaseServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function earlyInit(): void
    {
        $registry = Application::make('http.middleware');
        assert($registry instanceof StageMiddlewareRegistry);
        $registry->after('session.restore', new class () implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request)->withHeader('X-Foo-Plugin', 'on');
            }
        }, ['owner' => 'foo']);
    }

    public function init(): void
    {
        \ACMS_Hook::singleton()->attach('Foo', new Hook());
    }
}

final class Hook
{
    /**
     * @param string $contents
     */
    public function afterBuild(&$contents): void
    {
        $contents .= '<!-- foo-hook -->';
        $contents .= '<!-- foo-blog=' . Context::blogId() . ' -->';
    }
}
