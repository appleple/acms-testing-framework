<?php

namespace Acms\TestingFramework;

use Acms\Application;
use RuntimeException;

/**
 * a-blog cms 本体のオートロード・定数・アプリケーションコンテナを初期化するファサード。
 *
 * プラグインの `tests/bootstrap.php` から次の 2 行で呼び出す:
 *
 * ```php
 * require_once __DIR__ . '/../vendor/autoload.php';
 * \Acms\TestingFramework\Bootstrap::boot();
 * ```
 *
 * 本体パス（ACMS_ROOT）は引数、`phpunit.xml` の `<env />`、もしくは実行時の環境変数
 * （getenv）から受け取る。`.env.testing` の読み込みは ACMS_ROOT の解決より後に行われる
 * ため、`.env.testing` で ACMS_ROOT を上書きすることはできない（本体モノレポの
 * `tests/phpunit/bootstrap.php` のように呼び出し側で `__DIR__` から絶対パスを直接
 * 渡すのが最も安定する）。
 *
 * テスト用 DB 設定（ACMS_DB_* / ACMS_LICENSE_EDITION 等）は `.env.testing` の読み込み後に
 * 解決するため、引数・`<env />`・実行時の環境変数に加えて `.env.testing` でも上書きできる。
 * ただし `phpunit.xml` の `<env />` に既定値を書いてしまうと、Dotenv は「既に設定済みの
 * 環境変数を上書きしない」仕様のため `.env.testing` 側の値が常に無視される点に注意。
 */
final class Bootstrap
{
    private static bool $booted = false;

    /**
     * 本体を初期化する。冪等（複数回呼んでも 1 度しか実行しない）。
     *
     * @param string|null $acmsRoot a-blog cms 本体のルートディレクトリ。null の場合は環境変数 ACMS_ROOT を使う。
     */
    public static function boot(?string $acmsRoot = null): void
    {
        if (self::$booted) {
            return;
        }

        $acmsRoot = self::resolveAcmsRoot($acmsRoot);
        $bodyRoot = self::resolveBodyRoot($acmsRoot);

        if (!\defined('REQUEST_TIME')) {
            \define('REQUEST_TIME', \time());
        }
        if (!\defined('START_TIME')) {
            \define('START_TIME', \microtime(true));
        }

        // a-blog cms 本体のランタイム依存（vlucas/phpdotenv など）を読み込む。
        // テスト基盤自身の依存（phpunit / faker）は呼び出し側の vendor/autoload.php が担う。
        $acmsVendor = $bodyRoot . '/php/vendor/autoload.php';
        if (!\is_file($acmsVendor)) {
            throw new RuntimeException(
                '本体のオートローダが見つかりません: ' . $acmsVendor
                . ' （ACMS_ROOT が a-blog cms 本体のルート、もしくはその親〈ablogcms/ を含む〉を'
                . '指しているか確認してください）'
            );
        }
        require_once $acmsVendor;

        // テスト基盤のグローバルヘルパー（env()）。
        // composer の files オートロードには載せない（PHPStan/phpcs など vendor/autoload.php を
        // 読む全ツールで env() が eager 定義され、本体側 env() 定義と衝突するのを避けるため）。
        require_once __DIR__ . '/functions.php';

        // .env.testing
        if (\file_exists($acmsRoot . '/.env.testing')) {
            \Dotenv\Dotenv::createImmutable($acmsRoot, '.env.testing')->load();
        }

        self::defineConfigurationConstants();

        require_once $bodyRoot . '/php/config/app.php';
        require_once $bodyRoot . '/php/config/polyfill.php';
        if (\file_exists($bodyRoot . '/config.user.php')) {
            require_once $bodyRoot . '/config.user.php';
        }

        // path / custom autoload
        \setPath(\realpath($bodyRoot . '/index.php'));
        \spl_autoload_register('autoload');

        self::defineRequestEnvironment();
        self::defineApplicationConstants($bodyRoot);

        // Application コンテナ起動
        $config = \appConfig();
        $application = new Application();
        $application->init($config['aliases'], $config['providers']);

        // テスト環境では実 HTTP アップロードが無いため is_uploaded_file() をオーバーライドする
        require_once __DIR__ . '/Overrides/is_uploaded_file.php';

        self::$booted = true;
    }

    /**
     * 引数もしくは環境変数から本体ルートを解決し、存在を検証する。
     *
     * setPath() が内部で chdir() するため、相対パスのまま保持すると boot() の
     * 後半（chdir 後）で $acmsRoot を使う require が壊れる。ここで絶対パスに解決しておく。
     */
    private static function resolveAcmsRoot(?string $acmsRoot): string
    {
        if ($acmsRoot === null || $acmsRoot === '') {
            $acmsRoot = self::environmentValue('ACMS_ROOT', null);
        }

        if ($acmsRoot === null || !\is_dir($acmsRoot)) {
            throw new RuntimeException(
                'ACMS_ROOT が未設定または不正です。phpunit.xml の <env name="ACMS_ROOT" /> か、'
                . '実行時の環境変数、もしくは Bootstrap::boot() の引数で本体ルートを指定してください。'
            );
        }

        $resolved = \realpath($acmsRoot);

        return \rtrim($resolved !== false ? $resolved : $acmsRoot, '/');
    }

    /**
     * a-blog cms 本体（`index.php` / `php/` を持つディレクトリ）の位置を解決する。
     *
     * レイアウトが 2 通りあるため自動判定する:
     * - モノレポ / git 作業ツリー: 本体は `<ACMS_ROOT>/ablogcms/` 配下（`ablogcms/php/` が存在）。
     * - 配布物・本体同梱 Docker イメージ（`appleple/acms`）: 本体は `ACMS_ROOT` 直下に展開される
     *   （`/var/www/html/php/...`。`ablogcms/` の階層は無い）。
     *
     * `<ACMS_ROOT>/ablogcms/php` があれば前者、無ければ後者とみなす。判定を実機起動なしで検証
     * できるよう public にしている。
     */
    public static function resolveBodyRoot(string $acmsRoot): string
    {
        $acmsRoot = \rtrim($acmsRoot, '/');

        if (\is_dir($acmsRoot . '/ablogcms/php')) {
            return $acmsRoot . '/ablogcms';
        }

        return $acmsRoot;
    }

    /**
     * config.server.php 相当の定数をテスト用環境変数から定義する。
     */
    private static function defineConfigurationConstants(): void
    {
        $constants = [
            'DOMAIN' => self::environmentValue('ACMS_DOMAIN', ''),
            'DOMAIN_BASE' => self::environmentValue('ACMS_DOMAIN_BASE', ''),
            'DB_TYPE' => self::environmentValue('ACMS_DB_TYPE', 'mysql'),
            'DB_HOST' => self::environmentValue('ACMS_DB_HOST', '127.0.0.1'),
            'DB_NAME' => self::environmentValue('ACMS_DB_NAME', 'db_acms_test'),
            'DB_USER' => self::environmentValue('ACMS_DB_USER', 'root'),
            'DB_PASS' => self::environmentValue('ACMS_DB_PASS', 'root'),
            'DB_PORT' => self::environmentValue('ACMS_DB_PORT', '3306'),
            'DB_CHARSET' => 'UTF-8',
            'DB_CONNECTION_CHARSET' => self::environmentValue('ACMS_DB_CONNECTION_CHARSET', null),
            'DB_PREFIX' => self::environmentValue('ACMS_DB_PREFIX', 'acms_'),
            'DB_SLOW_QUERY_TIME' => (float) self::environmentValue('ACMS_DB_SLOW_QUERY_TIME', '0.3'),
            'GETTEXT_TYPE' => self::environmentValue('ACMS_GETTEXT_TYPE', 'user'),
            'GETTEXT_APPLICATION_RANGE' => self::environmentValue('ACMS_GETTEXT_APPLICATION_RANGE', 'all'),
            'GETTEXT_DEFAULT_LOCALE' => self::environmentValue('ACMS_GETTEXT_DEFAULT_LOCALE', 'ja_JP.UTF-8'),
            'GETTEXT_DOMAIN' => self::environmentValue('ACMS_GETTEXT_DOMAIN', 'messages'),
            'GETTEXT_PATH' => self::environmentValue('ACMS_GETTEXT_PATH', 'lang'),
            'TRUSTED_PROXY_LIST' => self::environmentValue('ACMS_TRUSTED_PROXY_LIST', ''),
            'PROXY_IP_HEADER' => self::environmentValue('ACMS_PROXY_IP_HEADER', 'HTTP_X_FORWARDED_FOR'),
            'PROXY_PORT' => self::environmentValue('ACMS_PROXY_PORT', ''),
            'PROXY_IP' => self::environmentValue('ACMS_PROXY_IP', ''),
            'CHMOD_DIR' => (0775 & ~\umask()),
            'CHMOD_FILE' => (0664 & ~\umask()),
            'SSL_ENABLE' => (int) self::environmentValue('ACMS_SSL_ENABLE', '0'),
            'FULLTIME_SSL_ENABLE' => (int) self::environmentValue('ACMS_FULLTIME_SSL_ENABLE', '0'),
            'COOKIE_SECURE' => (int) self::environmentValue('ACMS_COOKIE_SECURE', '0'),
            'COOKIE_HTTPONLY' => (int) self::environmentValue('ACMS_COOKIE_HTTPONLY', '1'),
            'COOKIE_SAME_SITE' => self::environmentValue('ACMS_COOKIE_SAME_SITE', 'Lax'),
            'HOOK_ENABLE' => (int) self::environmentValue('ACMS_HOOK_ENABLE', '1'),
            'RESOLVE_PATH' => (int) self::environmentValue('ACMS_RESOLVE_PATH', '1'),
            'URL_SUFFIX_SLASH' => (int) self::environmentValue('ACMS_URL_SUFFIX_SLASH', '1'),
            'SESSION_NAME' => self::environmentValue('ACMS_SESSION_NAME', 'sid'),
            'ACMS_HASH_NAME' => self::environmentValue('ACMS_HASH_NAME', 'acms_hash'),
            'REWRITE_FORCE' => (int) self::environmentValue('ACMS_REWRITE_FORCE', '1'),
            'MAX_PUBLISHES' => (int) self::environmentValue('ACMS_MAX_PUBLISHES', '15'),
            'MAX_EXECUTION_TIME' => (int) self::environmentValue('ACMS_MAX_EXECUTION_TIME', '-1'),
            'DEFAULT_TIMEZONE' => self::environmentValue('ACMS_DEFAULT_TIMEZONE', 'Asia/Tokyo'),
            'DOCUMENT_ROOT_FORCE' => self::environmentValue('ACMS_DOCUMENT_ROOT_FORCE', null),
            'PHP_SESSION_USE_DB' => (int) self::environmentValue('ACMS_PHP_SESSION_USE_DB', '0'),
            'THEMES_DIR' => self::environmentValue('ACMS_THEMES_DIR', 'themes/'),
            'ARCHIVES_DIR' => self::environmentValue('ACMS_ARCHIVES_DIR', 'archives/'),
            'MEDIA_LIBRARY_DIR' => self::environmentValue('ACMS_MEDIA_LIBRARY_DIR', 'media/'),
            'MEDIA_STORAGE_DIR' => self::environmentValue('ACMS_MEDIA_STORAGE_DIR', 'storage/'),
            'CACHE_DIR' => self::environmentValue('ACMS_CACHE_DIR', 'cache/'),
            'ARCHIVES_CACHE_SERVER' => self::environmentValue('ACMS_ARCHIVES_CACHE_SERVER', ''),
            'PHP_DIR' => self::environmentValue('ACMS_PHP_DIR', 'php/'),
            'JS_DIR' => self::environmentValue('ACMS_JS_DIR', 'js/'),
            'IMAGES_DIR' => self::environmentValue('ACMS_IMAGES_DIR', 'images/'),
            'CONFIG_FILE' => self::environmentValue('ACMS_CONFIG_FILE', 'private/config.system.yaml'),
            'CONFIG_DEFAULT_FILE' => self::environmentValue('ACMS_CONFIG_DEFAULT_FILE', 'private/config.system.default.yaml'),
            'MIME_TYPES_FILE' => self::environmentValue('ACMS_MIME_TYPES_FILE', 'private/mime.types'),
            'REWRITE_PATH_EXTENSION' => self::environmentValue(
                'ACMS_REWRITE_PATH_EXTENSION',
                'pdf|doc|docx|ppt|pptx|xls|xlsx|lzh|zip|rar'
            ),
            'ERROR_LOG_FILE' => self::environmentValue('ACMS_ERROR_LOG_FILE', ''),
            'ASYNC_PROCESS_LOG_PATH' => self::environmentValue('ACMS_ASYNC_PROCESS_LOG_PATH', ''),
            'PHP_PROCESS_BINARY' => self::environmentValue('ACMS_PHP_PROCESS_BINARY', ''),
            'BID_SEGMENT' => self::environmentValue('ACMS_BID_SEGMENT', 'bid'),
            'AID_SEGMENT' => self::environmentValue('ACMS_AID_SEGMENT', 'aid'),
            'UID_SEGMENT' => self::environmentValue('ACMS_UID_SEGMENT', 'uid'),
            'CID_SEGMENT' => self::environmentValue('ACMS_CID_SEGMENT', 'cid'),
            'EID_SEGMENT' => self::environmentValue('ACMS_EID_SEGMENT', 'eid'),
            'UTID_SEGMENT' => self::environmentValue('ACMS_UTID_SEGMENT', 'utid'),
            'CMID_SEGMENT' => self::environmentValue('ACMS_CMID_SEGMENT', 'cmid'),
            'KEYWORD_SEGMENT' => self::environmentValue('ACMS_KEYWORD_SEGMENT', 'keyword'),
            'TAG_SEGMENT' => self::environmentValue('ACMS_TAG_SEGMENT', 'tag'),
            'FIELD_SEGMENT' => self::environmentValue('ACMS_FIELD_SEGMENT', 'field'),
            'ORDER_SEGMENT' => self::environmentValue('ACMS_ORDER_SEGMENT', 'order'),
            'TPL_SEGMENT' => self::environmentValue('ACMS_TPL_SEGMENT', 'tpl'),
            'PAGE_SEGMENT' => self::environmentValue('ACMS_PAGE_SEGMENT', 'page'),
            'PROXY_SEGMENT' => self::environmentValue('ACMS_PROXY_SEGMENT', 'proxy'),
            'SPAN_SEGMENT' => self::environmentValue('ACMS_SPAN_SEGMENT', '-'),
            'ADMIN_SEGMENT' => self::environmentValue('ACMS_ADMIN_SEGMENT', 'admin'),
            'MEDIA_FILE_SEGMENT' => self::environmentValue('ACMS_MEDIA_FILE_SEGMENT', 'media-download'),
            'LOGIN_SEGMENT' => self::environmentValue('ACMS_LOGIN_SEGMENT', 'login'),
            'ADMIN_RESET_PASSWORD_SEGMENT' => self::environmentValue(
                'ACMS_ADMIN_RESET_PASSWORD_SEGMENT',
                'admin-reset-password'
            ),
            'ADMIN_RESET_PASSWORD_AUTH_SEGMENT' => self::environmentValue(
                'ACMS_ADMIN_RESET_PASSWORD_AUTH_SEGMENT',
                'admin-reset-password-auth'
            ),
            'ADMIN_TFA_RECOVERY_SEGMENT' => self::environmentValue(
                'ACMS_ADMIN_TFA_RECOVERY_SEGMENT',
                'admin-tfa-recovery'
            ),
            'SIGNIN_SEGMENT' => self::environmentValue('ACMS_SIGNIN_SEGMENT', 'signin'),
            'SIGNUP_SEGMENT' => self::environmentValue('ACMS_SIGNUP_SEGMENT', 'signup'),
            'RESET_PASSWORD_SEGMENT' => self::environmentValue('ACMS_RESET_PASSWORD_SEGMENT', 'reset-password'),
            'RESET_PASSWORD_AUTH_SEGMENT' => self::environmentValue(
                'ACMS_RESET_PASSWORD_AUTH_SEGMENT',
                'reset-password-auth'
            ),
            'TFA_RECOVERY_SEGMENT' => self::environmentValue('ACMS_TFA_RECOVERY_SEGMENT', 'tfa-recovery'),
            'PROFILE_UPDATE_SEGMENT' => self::environmentValue('ACMS_PROFILE_UPDATE_SEGMENT', 'mypage/update-profile'),
            'PASSWORD_UPDATE_SEGMENT' => self::environmentValue(
                'ACMS_PASSWORD_UPDATE_SEGMENT',
                'mypage/update-password'
            ),
            'EMAIL_UPDATE_SEGMENT' => self::environmentValue('ACMS_EMAIL_UPDATE_SEGMENT', 'mypage/update-email'),
            'TFA_UPDATE_SEGMENT' => self::environmentValue('ACMS_TFA_UPDATE_SEGMENT', 'mypage/update-tfa'),
            'WITHDRAWAL_SEGMENT' => self::environmentValue('ACMS_WITHDRAWAL_SEGMENT', 'mypage/withdrawal'),
            'LIMIT_SEGMENT' => self::environmentValue('ACMS_LIMIT_SEGMENT', 'limit'),
            'DOMAIN_SEGMENT' => self::environmentValue('ACMS_DOMAIN_SEGMENT', 'domain'),
            'API_SEGMENT' => self::environmentValue('ACMS_API_SEGMENT', 'api'),
            'IOS_APP_UA' => self::environmentValue('ACMS_IOS_APP_UA', 'acms_iOS_app'),
            'DEBUG_MODE' => (int) self::environmentValue('ACMS_DEBUG_MODE', '0'),
            'BENCHMARK_MODE' => (int) self::environmentValue('ACMS_BENCHMARK_MODE', '0'),
        ];

        foreach ($constants as $name => $value) {
            self::defineConstant($name, $value);
        }
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    private static function environmentValue(string $name, mixed $default): mixed
    {
        if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
            return $_ENV[$name];
        }

        $value = \getenv($name);
        if ($value !== false && $value !== '') {
            return $value;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    private static function defineConstant(string $name, mixed $value): void
    {
        if (!\defined($name)) {
            \define($name, $value);
        }
    }

    /**
     * リクエスト由来のグローバル（$_SERVER）を CLI 実行向けに補完する。
     */
    private static function defineRequestEnvironment(): void
    {
        $_SERVER['REQUEST_METHOD'] ??= 'GET';
        $_SERVER['HTTP_HOST'] ??= '';
        $_SERVER['REQUEST_URI'] ??= '';
        $_SERVER['QUERY_STRING'] ??= '';
    }

    /**
     * 本体がリクエスト処理中に確定させるグローバル定数を、テスト向けの既定値で定義する。
     *
     * 単純な既定値スタブで足りるもの（BID〜SYSTEM_GENERATED_DATETIME）は standalone.php と
     * 共有する単一の真実の源 `ablogcms/php/config/cli_constants.php` から読み込む。
     * それ以外（$host 等リクエスト実体から main.php が動的に算出する定数）は静的な一次情報を
     * 持たないため、テスト実行のための既定値スタブとしてここでのみ定義する。
     */
    private static function defineApplicationConstants(string $bodyRoot): void
    {
        require_once $bodyRoot . '/php/config/cli_constants.php';

        $constants = [
            'ACMS_POST' => '',
            'ACMS_NO_REWRITE' => 'acms_no_rewrite',
            'COOKIE_HOST' => '',
            'MIME_TYPE' => '',
            'IS_TRIAL' => false,
            'TRIAL_COUNT_DOWN' => 0,
            'ROOT_TPL' => '',
            'IS_AUTH_SYSTEM_PAGE' => 0,
            'IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE' => 0,
            'IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE' => 0,
            'IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE' => 0,
            'IS_SYSTEM_LOGIN_PAGE' => 0,
            'IS_SYSTEM_RESET_PASSWORD_PAGE' => 0,
            'IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE' => 0,
            'IS_SYSTEM_SIGNIN_PAGE' => 0,
            'IS_SYSTEM_SIGNUP_PAGE' => 0,
            'IS_SYSTEM_TFA_RECOVERY_PAGE' => 0,
            'IS_UPDATE_EMAIL_PAGE' => 0,
            'IS_UPDATE_PASSWORD_PAGE' => 0,
            'IS_UPDATE_PROFILE_PAGE' => 0,
            'IS_UPDATE_TFA_PAGE' => 0,
            'IS_WITHDRAWAL_PAGE' => 0,
            'IS_REVISION_PREVIEW_PAGE' => 0,
            'NO_CACHE_PAGE' => 0,
            'ROOT_DIR' => '',
            'SETUP_DIR' => '',
        ];
        foreach ($constants as $name => $value) {
            self::defineConstant($name, $value);
        }

        if (!\defined('LICENSE_EDITION')) {
            \define('LICENSE_EDITION', self::environmentValue('ACMS_LICENSE_EDITION', 'professional'));
        }
    }
}
