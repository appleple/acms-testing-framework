<?php

namespace Acms\TestingFramework\Helpers;

use Acms\Services\Facades\Database;
use SQL;

/**
 * 統合テストでプラグイン（拡張アプリ）の有効化状態を切り替えるヘルパー。
 *
 * a-blog cms は拡張アプリの有効/無効を `app` テーブルの行（`app_name` = アプリの
 * クラス名、`app_status` = on|off、`app_blog_id` = ブログ）で永続化する。本クラスは
 * その永続化フラグを直接操作する。`ACMS_POST_App_Activate` / `_Deactivate` から
 * CSRF・セッション・ロギングを取り除いた最小版に相当する。
 *
 * 注意: ここで操作するのは「永続化された有効化フラグ」だけである。拡張アプリの
 * ServiceProvider 登録は本体のリクエスト処理（main.php が `app` テーブルを読んで行う）
 * でのみ行われ、{@see \Acms\TestingFramework\Bootstrap::boot()} は固定の ServiceProvider
 * リスト（`appConfig()`）を登録するだけで `app` テーブルを見ない。そのため、テスト中に
 * このクラスで有効化フラグを変えても、同一プロセス内では対象アプリの ServiceProvider や
 * Hook は登録されない。フラグに依存するロジックを検証する用途で使う。
 *
 * 引数にはプラグイン名（例: 'SamplePlugin'）を渡す。新形式の規約に従って ServiceProvider の
 * クラス名（`Acms\Plugins\{名前}\ServiceProvider`。コアが `app_name` に保存する get_class 相当）へ
 * 補完する。ServiceProvider が規約と異なる場所にある場合は、その FQCN を直接渡せばそのまま使う。
 */
final class PluginActivator
{
    /**
     * 拡張アプリを有効化する（`app` 行を upsert して status を on にする）。
     *
     * @param string $plugin プラグイン名（例: 'SamplePlugin'）。ServiceProvider の FQCN を直接渡してもよい。
     * @param int|null $blogId 対象ブログ。null の場合は現在のブログ（定数 BID）
     * @param string $version app_version に記録するバージョン文字列
     */
    public static function activate(string $plugin, ?int $blogId = null, string $version = ''): void
    {
        $appName = self::resolveAppName($plugin);
        $blogId = self::resolveBlogId($blogId);
        $datetime = date('Y-m-d H:i:s');

        if (self::exists($appName, $blogId)) {
            $sql = SQL::newUpdate('app');
            $sql->addUpdate('app_status', 'on');
            $sql->addUpdate('app_activate_datetime', $datetime);
            $sql->addWhereOpr('app_name', $appName);
            $sql->addWhereOpr('app_blog_id', $blogId);
            Database::query($sql->get(dsn()), 'exec');
            return;
        }

        $sql = SQL::newInsert('app');
        $sql->addInsert('app_name', $appName);
        $sql->addInsert('app_version', $version);
        $sql->addInsert('app_status', 'on');
        $sql->addInsert('app_activate_datetime', $datetime);
        $sql->addInsert('app_install_datetime', $datetime);
        $sql->addInsert('app_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * 拡張アプリを無効化する（`app` 行が存在すれば status を off にする）。
     *
     * @param string $plugin プラグイン名。ServiceProvider の FQCN を直接渡してもよい。
     * @param int|null $blogId 対象ブログ。null の場合は現在のブログ（定数 BID）
     */
    public static function deactivate(string $plugin, ?int $blogId = null): void
    {
        $appName = self::resolveAppName($plugin);
        $blogId = self::resolveBlogId($blogId);

        $sql = SQL::newUpdate('app');
        $sql->addUpdate('app_status', 'off');
        $sql->addWhereOpr('app_name', $appName);
        $sql->addWhereOpr('app_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * 拡張アプリが有効化されているかを返す。
     *
     * @param string $plugin プラグイン名。ServiceProvider の FQCN を直接渡してもよい。
     * @param int|null $blogId 対象ブログ。null の場合は現在のブログ（定数 BID）
     */
    public static function isActive(string $plugin, ?int $blogId = null): bool
    {
        $appName = self::resolveAppName($plugin);
        $blogId = self::resolveBlogId($blogId);

        $sql = SQL::newSelect('app');
        $sql->addSelect('app_status');
        $sql->addWhereOpr('app_name', $appName);
        $sql->addWhereOpr('app_blog_id', $blogId);

        return Database::query($sql->get(dsn()), 'one') === 'on';
    }

    /**
     * プラグイン名を app_name（拡張アプリの ServiceProvider の FQCN）へ補完する。
     *
     * 新形式プラグインの規約 `Acms\Plugins\{名前}\ServiceProvider` に合わせる。これはコアが
     * 有効化時に `app_name` へ保存する get_class($App) と一致する。すでに名前空間付きの
     * クラス名（`\` を含む）が渡された場合は、規約に依らずそのまま app_name として使う。
     *
     * @param string $plugin プラグイン名、または ServiceProvider の FQCN
     */
    private static function resolveAppName(string $plugin): string
    {
        if (\str_contains($plugin, '\\')) {
            return \ltrim($plugin, '\\');
        }
        return 'Acms\\Plugins\\' . $plugin . '\\ServiceProvider';
    }

    private static function exists(string $appName, int $blogId): bool
    {
        $sql = SQL::newSelect('app');
        $sql->addSelect('app_name');
        $sql->addWhereOpr('app_name', $appName);
        $sql->addWhereOpr('app_blog_id', $blogId);
        $sql->setLimit(1);

        return Database::query($sql->get(dsn()), 'one') !== false;
    }

    private static function resolveBlogId(?int $blogId): int
    {
        if ($blogId !== null) {
            return $blogId;
        }
        return \defined('BID') ? BID : 1;
    }
}
