<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Http;

use Acms\Services\Http\Termination\Terminator;
use Acms\TestingFramework\Http\CapturesTermination;
use Acms\TestingFramework\Internal\ThrowingTerminationHandler;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\TestDox;

final class CapturesTerminationTest extends TestCase
{
    use CapturesTermination;

    protected function tearDown(): void
    {
        Terminator::reset();
        parent::tearDown();
    }

    #[Test]
    #[TestDox('終了を応答として受け取り、差し替えは元（未差し替え）へ戻る')]
    public function 終了を受け取って戻す(): void
    {
        $response = $this->captureTermination(static function (): void {
            Terminator::terminateWith('body', [], 418);
        });

        $this->assertSame(418, $response?->getStatusCode());
        $this->assertNull(Terminator::currentHandler());
    }

    #[Test]
    #[TestDox('入れ子で呼んでも、内側が終わったあとは外側の差し替えが残る')]
    public function 入れ子でも外側を消さない(): void
    {
        $outer = new ThrowingTerminationHandler();
        Terminator::useHandler($outer);

        $this->captureTermination(static fn (): null => null);

        $this->assertSame($outer, Terminator::currentHandler());
    }

    #[Test]
    #[TestDox('handleCapturingTermination() も、終了を応答として返し、差し替え前へ戻す')]
    public function 応答を返す処理でも戻す(): void
    {
        $outer = new ThrowingTerminationHandler();
        Terminator::useHandler($outer);

        $response = $this->handleCapturingTermination(static function (): \Psr\Http\Message\ResponseInterface {
            Terminator::terminateWith('x', [], 503);
        });

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame($outer, Terminator::currentHandler());
    }

    #[Test]
    #[TestDox('終了が catch (\Throwable) に握りつぶされて、そのまま処理が終わったときは、テストを失敗させる')]
    public function 握りつぶされて終わったら失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('握りつぶしています');

        $this->captureTermination(static function (): void {
            try {
                Terminator::terminateWith('first', [], 200);
            } catch (\Throwable) {
                // 本番は exit で止まるため、ここへは来ない
            }
        });
    }

    #[Test]
    #[TestDox('握りつぶしたあとで別の終了へ進み、2 つ目の終了の例外が届いたときも、テストを失敗させる（最初の応答を取り違えない）')]
    public function 握りつぶして別の終了へ進んだら失敗する(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('終了した回数: 2');

        $this->captureTermination(static function (): void {
            try {
                Terminator::terminateWith('first', [], 200);
            } catch (\Throwable $exception) {
                Terminator::terminateWith($exception->getMessage(), [], 500);
            }
        });
    }

    #[Test]
    #[TestDox('handleCapturingTermination() でも、握りつぶされて応答が戻ったときは、テストを失敗させる')]
    public function 応答を返す処理でも握りつぶしを検出する(): void
    {
        $this->expectException(AssertionFailedError::class);

        $this->handleCapturingTermination(static function (): \Psr\Http\Message\ResponseInterface {
            try {
                Terminator::terminateWith('first', [], 200);
            } catch (\Throwable) {
            }

            return (new \Acms\Services\RestApi\Http\MessageFactory())->response(200);
        });
    }

    #[Test]
    #[TestDox('握りつぶしを検出して失敗したあとも、差し替えは元へ戻る')]
    public function 失敗しても差し替えを戻す(): void
    {
        try {
            $this->captureTermination(static function (): void {
                try {
                    Terminator::terminateWith('first', [], 200);
                } catch (\Throwable) {
                }
            });
            $this->fail('失敗にならなかった');
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString('握りつぶしています', $failure->getMessage());
        }

        $this->assertNull(Terminator::currentHandler());
    }

    #[Test]
    #[TestDox('終了が起きなかったとき、握りつぶしとは見なさない')]
    public function 終了しなければ失敗しない(): void
    {
        $this->assertNull($this->captureTermination(static fn (): null => null));
    }
}
