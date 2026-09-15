<?php

/**
 * PHPUnit bootstrap エントリ（プラグイン用の共有 bootstrap）。
 *
 * プラグイン側は phpunit.xml で
 *
 *   bootstrap="vendor/ablogcms/testing-framework/bootstrap.php"
 *
 * と指すだけでよく、各プラグインが tests/bootstrap.php を自前で持つ必要がなくなる。
 * 本体パス（ACMS_ROOT）とテスト用 DB 設定は、従来どおり phpunit.xml の <env /> か
 * 実行時の環境変数、もしくは本体の .env.testing から解決される（このファイルはそれらを
 * 一切固定しないため、消費側の設定がそのまま効く）。
 *
 * vendor/bin/phpunit は起動時に消費側の vendor/autoload.php を読み込むため、通常この
 * 時点で \Acms\TestingFramework\Bootstrap は既にオートロード可能。素の php で直接このファイルを
 * 実行した場合などに備え、未ロード時のみパッケージ位置から消費側オートローダを読む
 * （vendor/ablogcms/testing-framework/bootstrap.php → vendor/autoload.php）。
 *
 * 追加の初期化（フィクスチャ読み込み・独自オートロードパス登録など）が必要なプラグインは、
 * 自前の tests/bootstrap.php でこのファイルを require してから拡張すればよい（このファイルの
 * 置き換えではなく上乗せができる）。
 */

if (!\class_exists(\Acms\TestingFramework\Bootstrap::class)) {
    $projectAutoload = __DIR__ . '/../../autoload.php';
    if (\is_file($projectAutoload)) {
        require_once $projectAutoload;
    }
}

\Acms\TestingFramework\Bootstrap::boot();
