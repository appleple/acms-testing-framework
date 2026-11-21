<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Readme;

use Acms\TestingFramework\Http\CapturesTermination;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * README の「関数の奥の終了を直接受け取る」の例を、そのまま写したテスト（README を直したらここも直す）
 */
final class FooHandlerTest extends TestCase
{
    use CapturesTermination;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../Fixtures/Readme/FooHandlers.php';
    }

    #[Test]
    public function 権限が無ければ403で終わる(): void
    {
        $response = $this->captureTermination(fn () => (new \ACMS_POST_Foo_Update())->post());   // 終了したらその応答、しなかったら null

        $this->assertSame(403, $response?->getStatusCode());
    }
}
