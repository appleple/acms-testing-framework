<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Http;

use Acms\Services\Http\Termination\Terminator;
use Acms\TestingFramework\Internal\ThrowingTerminationHandler;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * 関数の奥の終了（Terminator）を、テストで応答として受け取る
 *
 * 差し替えは `finally` で、差し替え前のハンドラへ必ず戻すため、例外やアサーションの失敗で他のテストへ残らず、入れ子にしても外側は消えない。
 *
 * 本番の終了は `exit` で、`catch` では止められない。テストでは例外で運ぶため、`catch (\Throwable)` が握りつぶすと、処理が終了の後ろへ進み、
 * 応答が受け取れない（または、握りつぶしたあとの別の終了の応答を受け取る）。この取り違えを見逃さないよう、終了が起きたのに最初の終了の例外が
 * 届かなかったとき（握りつぶし）は、テストを失敗させる。
 * ただし、握りつぶしたあとで終了の例外以外の例外が外へ出た場合は、その例外がそのまま伝わり、ここでは検出しない
 * （そのテストが `expectException()` でその例外を期待していると、握りつぶしを見逃す）。
 */
trait CapturesTermination
{
    /**
     * `$action` を実行し、処理が終了したらその応答を返す。終了しなかったときは null
     *
     * @param \Closure(): mixed $action
     */
    protected function captureTermination(\Closure $action): ?ResponseInterface
    {
        $previous = Terminator::currentHandler();
        $handler = new ThrowingTerminationHandler();
        Terminator::useHandler($handler);
        try {
            $action();
        } catch (TerminationException $exception) {
            $this->assertTerminationWasNotSwallowed($handler, $exception);

            return $exception->getResponse();
        } finally {
            // 一律に解除せず、差し替え前のハンドラへ戻す（入れ子で呼ばれたとき、外側の差し替えまで消さない）
            Terminator::useHandler($previous);
        }
        $this->assertTerminationWasNotSwallowed($handler, null);

        return null;
    }

    /**
     * 応答を返す処理（`HttpKernel::handle()` など）を実行する。途中で関数の奥の終了が起きたら、その応答を戻り値として返す
     *
     * @param \Closure(): ResponseInterface $handle
     */
    protected function handleCapturingTermination(\Closure $handle): ResponseInterface
    {
        $previous = Terminator::currentHandler();
        $handler = new ThrowingTerminationHandler();
        Terminator::useHandler($handler);
        try {
            $response = $handle();
        } catch (TerminationException $exception) {
            $this->assertTerminationWasNotSwallowed($handler, $exception);

            return $exception->getResponse();
        } finally {
            // 一律に解除せず、差し替え前のハンドラへ戻す（入れ子で呼ばれたとき、外側の差し替えまで消さない）
            Terminator::useHandler($previous);
        }
        $this->assertTerminationWasNotSwallowed($handler, null);

        return $response;
    }

    /**
     * 終了が 1 度も起きていない、または起きた 1 度の終了の例外がそのまま届いたことを確かめる
     *
     * 終了は 1 度で処理を止めるため、2 度以上起きた（握りつぶしたあとで、別の終了へ進んだ）、または届いた例外が最初のものでない、
     * もしくは終了が起きたのに例外が届かない場合は、`catch (\Throwable)` などが終了を握りつぶしている。
     */
    private function assertTerminationWasNotSwallowed(ThrowingTerminationHandler $handler, ?TerminationException $delivered): void
    {
        $thrown = $handler->thrown();
        if ($thrown === [] && $delivered === null) {
            return;
        }
        Assert::assertSame(
            [$delivered],
            $thrown,
            '終了（die403() など）の例外が、呼び出し側まで届きませんでした。`catch (\\Throwable)` が握りつぶしています（本番は exit で止まるため握りつぶせず、'
            . '終了の応答は 1 つだけ。応答を返す処理は try の外へ出すか、テストの期待を直してください）。終了した回数: ' . count($thrown)
        );
    }
}
