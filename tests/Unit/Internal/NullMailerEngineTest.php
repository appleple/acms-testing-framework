<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Unit\Internal;

use Acms\Services\Facades\Application;
use Acms\Services\Mailer\Engine;
use Acms\TestingFramework\Internal\NullMailerEngine;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

#[CoversClass(NullMailerEngine::class)]
final class NullMailerEngineTest extends TestCase
{
    #[Test]
    #[TestDox('テスト基盤の起動後、コンテナの mailer は、外へ送らないエンジンに解決される')]
    public function 既定のmailerは外へ送らない(): void
    {
        $this->assertInstanceOf(NullMailerEngine::class, Application::make('mailer'));
        $this->assertInstanceOf(Engine::class, Application::make('mailer'));
    }

    #[Test]
    #[TestDox('SMTP・sendmail の設定が無くても初期化でき、送信は例外にならず、実際には送られない')]
    public function 設定なしで初期化し送信しても例外にならない(): void
    {
        $engine = (new NullMailerEngine())->init();
        $engine->setFrom('from@example.test')->setTo('to@example.test')->setSubject('件名')->setBody('本文');

        $this->assertSame($engine, $engine->send());
    }
}
