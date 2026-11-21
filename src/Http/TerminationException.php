<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * テストのとき、関数の奥の終了（`die403()`・`redirect()`・`Response::json()` など）が投げる、応答を運ぶ例外
 *
 * 本番は送出して `exit` するため、これは投げられない。
 * Why \Error: `catch (\Exception $e)` が処理の失敗として握りつぶし、終了の後ろへ進むのを避ける（`exit` は `catch` で止められない）。
 * `catch (\Throwable)` には捕まるため、ThrowingTerminationHandler が投げた例外を記録し、CapturesTermination が、握りつぶされて届かなかったときにテストを失敗させる。
 */
final class TerminationException extends \Error
{
    public function __construct(private readonly ResponseInterface $response)
    {
        parent::__construct('terminated: ' . $response->getStatusCode());
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
