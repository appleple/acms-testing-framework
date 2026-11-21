<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

/**
 * 機能テストが一時的に置くもの（一時テーマ・復元状態のファイル）のうち、強制終了（SIGKILL など）で後始末が走らず残ったものを掃除する
 *
 * 更新から一定時間以上たったものだけを消す。同時に走る別のプロセスが使っているものを消さないため。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class StaleTemporaryFiles
{
    /**
     * 残骸とみなすまでの時間（秒）
     */
    public const AFTER_SECONDS = 86400;

    /**
     * @param string $pattern glob のパターン
     * @param string $namePattern 消してよい名前（basename）の正規表現。glob より厳しく絞る（無関係なものを消さないため）
     */
    public static function remove(string $pattern, string $namePattern, ?int $now = null): void
    {
        $now ??= time();
        foreach (glob($pattern) ?: [] as $path) { // @phpstan-ignore ternary.shortNotAllowed (glob() は失敗時 false。空配列で扱う)
            if (preg_match($namePattern, basename($path)) !== 1 || $now - (int) filemtime($path) < self::AFTER_SECONDS) {
                continue;
            }
            self::delete($path);
        }
    }

    /**
     * ファイルまたはディレクトリ（中身ごと）を消す。シンボリックリンクはたどらず、リンクだけを消す
     */
    public static function delete(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $file) { // @phpstan-ignore ternary.shortNotAllowed (scandir() は失敗時 false。空配列で扱う)
                if ($file !== '.' && $file !== '..') {
                    self::delete($path . '/' . $file);
                }
            }
            rmdir($path);

            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
        }
    }
}
