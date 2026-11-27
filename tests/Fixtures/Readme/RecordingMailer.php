<?php

declare(strict_types=1);

namespace Acms\Plugins\Foo\Tests;

use Acms\Services\Mailer\Engine;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;

/**
 * README の例が、プラグインがメールを送る処理のテストで `mailer` と差し替える、送信を記録する偽物
 */
final class RecordingMailer extends Engine
{
    /**
     * @var list<list<mixed>> 送信した宛先
     */
    public static array $sent = [];

    public function init()
    {
        $this->setMailer(new Mailer(new NullTransport()));

        return $this;
    }

    public function send($removeAttachedFiles = true)
    {
        self::$sent[] = array_map('strval', $this->to);

        return parent::send($removeAttachedFiles);
    }
}
