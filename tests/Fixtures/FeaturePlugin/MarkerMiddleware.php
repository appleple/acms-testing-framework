<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Fixtures\FeaturePlugin;

use Acms\Services\Http\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * 応答にヘッダーを足すだけの、プラグインのミドルウェアに見立てた部品（`X-Feature-Plugin` で、動いたことを示す印を付ける）
 *
 * `X-Feature-Plugin-Blog` には、公開の読み口（`Context`）で読んだブログの ID を入れる（定数の `BID` ではなく）。
 * ミドルウェアの位置によっては文脈が未確定なので、`isBlogIdDetermined()` で確かめてから読む
 */
final class MarkerMiddleware implements MiddlewareInterface
{
    /**
     * 動いた回数（リクエストをまたいで登録が積み上がっていないことを確かめるため）
     */
    public static int $calls = 0;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ++self::$calls;

        $blogId = Context::isBlogIdDetermined() ? (string) Context::blogId() : 'undetermined';

        return $handler->handle($request)->withHeader('X-Feature-Plugin', 'middleware')->withHeader('X-Feature-Plugin-Blog', $blogId);
    }
}
