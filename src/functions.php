<?php

/**
 * テスト基盤が必要とするグローバルヘルパー関数。
 *
 * {@see \Acms\TestingFramework\Bootstrap::boot()} から require される。
 * composer の files オートロードには載せない（PHPStan/phpcs など vendor/autoload.php を読む全ツールで env() が
 * eager 定義され、本体側の env() 定義と衝突するのを避けるため）。本体側や他ライブラリが
 * 同名関数を先に定義しているケースに備えて function_exists でガードする。
 */

if (!function_exists('env')) {
    /**
     * 環境変数を取得する（.env.testing 読み込み後に $_ENV / getenv() から引く）。
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    function env(string $key, string $default = ''): string
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}
