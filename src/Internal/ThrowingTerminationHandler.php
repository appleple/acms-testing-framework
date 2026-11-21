<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Http\Termination\TerminationHandler;
use Acms\TestingFramework\Http\TerminationException;
use Psr\Http\Message\ResponseInterface;

/**
 * 送出も `exit` もせず、応答を持った TerminationException を投げるテスト用の TerminationHandler
 *
 * 投げた例外を記録する。`exit` は `catch` で止められないが、例外は `catch (\Throwable)` が握りつぶせるため、
 * 呼ばれたのに例外が呼び出し側（CapturesTermination）まで届かなかった（握りつぶされた）ことを、あとから検出できるようにする。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class ThrowingTerminationHandler implements TerminationHandler
{
    /**
     * @var list<TerminationException>
     */
    private array $thrown = [];

    public function terminate(ResponseInterface $response): never
    {
        $exception = new TerminationException($response);
        $this->thrown[] = $exception;

        throw $exception;
    }

    /**
     * 終了のために投げた例外（呼ばれた順）
     *
     * @return list<TerminationException>
     */
    public function thrown(): array
    {
        return $this->thrown;
    }
}
