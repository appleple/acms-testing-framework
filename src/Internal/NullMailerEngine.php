<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Services\Mailer\Engine;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\NullTransport;

/**
 * 何も送らないメールエンジン（テスト基盤の起動時に、コンテナの `mailer` の既定として登録する）
 *
 * 本体の `Engine::init()` は、SMTP・sendmail の設定からトランスポートを作る。テストの環境にその設定があると（開発環境の `config.system.yaml`・
 * `.env`）、プラグインが `Mailer` を通して送るメールが、DB の巻き戻しとは無関係に、実際に外へ出てしまう。ここで既定を `NullTransport` にして、
 * 既定では外へ出ないようにする。メールの内容を確かめたいテストは、`Container::override('mailer', ...)` で記録用の偽物に差し替える。
 *
 * 本番には影響しない（登録するのは `Bootstrap::boot()` だけで、本番の起動経路は通らない）。
 *
 * @internal テスト基盤の部品。プラグインのテストからは使わない
 */
final class NullMailerEngine extends Engine
{
    public function init()
    {
        $this->setMailer(new Mailer(new NullTransport()));

        return $this;
    }
}
