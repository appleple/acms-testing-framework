<?php

/**
 * 自動生成物（tools/generate-acms-stubs.php / composer stubs）。手で編集しないこと。
 * 本体 phpstan/constants.php（設定・CMS 定数）のミラー。bootstrapFiles として読み込み、
 * 定数の存在を PHPStan に教える。値は型を持つダミーで、値の動的性は dynamic-constants.neon
 * が担う。リクエスト由来（SUID / CID / RBID ...）の定数は固定値 define だと型が縮退して
 * 偽陽性を生むため、ここには含めず acms-runtime.stub 側で union 型として供給する。
 */

define('DOMAIN', '');
define('DOMAIN_BASE', '');

define('DB_TYPE', 'mysql');
define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');
define('DB_PORT', null);
define('DB_CHARSET', 'UTF-8');
define('DB_CONNECTION_CHARSET', null);
define('DB_PREFIX', '');
define('DB_SLOW_QUERY_TIME', 0.3);

// GETTEXT_TYPE: fix|user|auto
define('GETTEXT_TYPE', 'user');
// GETTEXT_APPLICATION_RANGE: admin|login|all
define('GETTEXT_APPLICATION_RANGE', 'all');
define('GETTEXT_DEFAULT_LOCALE', 'ja_JP.UTF-8');
define('GETTEXT_DOMAIN', 'messages');
define('GETTEXT_PATH', 'lang');

// プロキシが入っている場合、X-Forwarded-ForヘッダーからクライアントIPアドレスを特定するため、
// 信頼できるプロキシのIPを設定します。 例: define('TRUSTED_PROXY_LIST', 'xxx.xxx.xxx.xxx,yyy.yyy.yyy.yyy');
define('TRUSTED_PROXY_LIST', '');
// プロキシでIPアドレスを追加するヘッダーを指定します。
define('PROXY_IP_HEADER', 'HTTP_X_FORWARDED_FOR');
// オンラインアップデート、Webhook、SNSログイン（LINE）のみ対応
define('PROXY_PORT', '');
define('PROXY_IP', '');

// CMSで作成するディレクトリ・ファイルのパーミッションを設定します
define('CHMOD_DIR', (0775 & ~ umask()));
define('CHMOD_FILE', (0664 & ~ umask()));

define('SSL_ENABLE', 0);
define('FULLTIME_SSL_ENABLE', 0);
define('COOKIE_SECURE', 0);
define('COOKIE_HTTPONLY', 1);
define('COOKIE_SAME_SITE', 'Lax');
define('HOOK_ENABLE', 0);
define('RESOLVE_PATH', 1);
define('URL_SUFFIX_SLASH', 1);
define('SESSION_NAME', 'sid');
define('ACMS_HASH_NAME', 'acms_hash');
define('REWRITE_FORCE', 1);
define('MAX_PUBLISHES', 15);
define('MAX_EXECUTION_TIME', -1); // phpstanの実行時間上限を無制限にするため、-1を指定
define('DEFAULT_TIMEZONE', 'Asia/Tokyo');
define('DOCUMENT_ROOT_FORCE', null);
define('PHP_SESSION_USE_DB', 0);

define('THEMES_DIR', 'themes/');
define('ARCHIVES_DIR', 'archives/');
define('MEDIA_LIBRARY_DIR', 'media/');
define('MEDIA_STORAGE_DIR', 'storage/');
define('CACHE_DIR', 'cache/');
define('ARCHIVES_CACHE_SERVER', '');
define('PHP_DIR', 'php/');
define('JS_DIR', 'js/');
define('IMAGES_DIR', 'images/');

define('CONFIG_FILE', 'private/config.system.yaml');
define('CONFIG_DEFAULT_FILE', 'private/config.system.default.yaml');
define('MIME_TYPES_FILE', 'private/mime.types');
define('REWRITE_PATH_EXTENSION', 'pdf|doc|docx|ppt|pptx|xls|xlsx|lzh|zip|rar');
define('ERROR_LOG_FILE', '');
define('ASYNC_PROCESS_LOG_PATH', ''); // ディレクトリを指定
// 非同期処理（システム更新, フォーム送信...）でPHPパスが合わない場合に使用。例1: PHP_BINDIR . '/php -c /path/to/php.ini' 例2: C:\xampp\php\php.exe
define('PHP_PROCESS_BINARY', '');

define('BID_SEGMENT', 'bid');
define('AID_SEGMENT', 'aid');
define('UID_SEGMENT', 'uid');
define('CID_SEGMENT', 'cid');
define('EID_SEGMENT', 'eid');
define('UTID_SEGMENT', 'utid');
define('CMID_SEGMENT', 'cmid');
define('KEYWORD_SEGMENT', 'keyword');
define('TAG_SEGMENT', 'tag');
define('FIELD_SEGMENT', 'field');
define('ORDER_SEGMENT', 'order');
define('TPL_SEGMENT', 'tpl');
define('PAGE_SEGMENT', 'page');
define('PROXY_SEGMENT', 'proxy');
define('SPAN_SEGMENT', '-');
define('ADMIN_SEGMENT', 'admin');
define('MEDIA_FILE_SEGMENT', 'media-download');
define('LOGIN_SEGMENT', 'login');
define('ADMIN_RESET_PASSWORD_SEGMENT', 'admin-reset-password');
define('ADMIN_RESET_PASSWORD_AUTH_SEGMENT', 'admin-reset-password-auth');
define('ADMIN_TFA_RECOVERY_SEGMENT', 'admin-tfa-recovery');
define('SIGNIN_SEGMENT', 'signin');
define('SIGNUP_SEGMENT', 'signup');
define('RESET_PASSWORD_SEGMENT', 'reset-password');
define('RESET_PASSWORD_AUTH_SEGMENT', 'reset-password-auth');
define('TFA_RECOVERY_SEGMENT', 'tfa-recovery');
define('PROFILE_UPDATE_SEGMENT', 'mypage/update-profile');
define('PASSWORD_UPDATE_SEGMENT', 'mypage/update-password');
define('EMAIL_UPDATE_SEGMENT', 'mypage/update-email');
define('TFA_UPDATE_SEGMENT', 'mypage/update-tfa');
define('WITHDRAWAL_SEGMENT', 'mypage/withdrawal');
define('LIMIT_SEGMENT', 'limit');
define('DOMAIN_SEGMENT', 'domain');
define('API_SEGMENT', 'api');
define('IOS_APP_UA', 'acms_iOS_app');

// 本番運用時は DEBUG_MODE を必ず 0 に設定して下さい
define('DEBUG_MODE', 0);
define('BENCHMARK_MODE', 0);

/**
 * constants for a-blog cms
 */
define('ACMS_POST', '');
define('ACMS_NO_REWRITE', 'acms_no_rewrite');
define('COOKIE_HOST', '');
define('MIME_TYPE', '');
define('TRIAL_COUNT_DOWN', 0);
define('ROOT_TPL', '');
define('IS_AUTH_SYSTEM_PAGE', 0);
define('IS_SYSTEM_ADMIN_RESET_PASSWORD_AUTH_PAGE', 0);
define('IS_SYSTEM_ADMIN_RESET_PASSWORD_PAGE', 0);
define('IS_SYSTEM_ADMIN_TFA_RECOVERY_PAGE', 0);
define('IS_SYSTEM_LOGIN_PAGE', 0);
define('IS_SYSTEM_RESET_PASSWORD_PAGE', 0);
define('IS_SYSTEM_RESET_PASSWORD_AUTH_PAGE', 0);
define('IS_SYSTEM_SIGNIN_PAGE', 0);
define('IS_SYSTEM_SIGNUP_PAGE', 0);
define('IS_SYSTEM_TFA_RECOVERY_PAGE', 0);
define('IS_UPDATE_EMAIL_PAGE', 0);
define('IS_UPDATE_PASSWORD_PAGE', 0);
define('IS_UPDATE_PROFILE_PAGE', 0);
define('IS_UPDATE_TFA_PAGE', 0);
define('IS_WITHDRAWAL_PAGE', 0);
define('IS_REVISION_PREVIEW_PAGE', 0);
define('NO_CACHE_PAGE', 0);

/**
 * constants for setup
 */
define('ROOT_DIR', '');
define('SETUP_DIR', '');

/**
 * constants for plugin
 *
 * config/app.php の setPath() 関数内で実行時に define されるパス定数。関数本体内の define は
 * 静的解析で拾えず、この定数を使うプラグインの ServiceProvider 等（PLUGIN_DIR . 'Foo/...'）で
 * 「Constant not found」になるため、存在解決用にここへ置く（値は dynamicConstantNames で動的扱い。
 * 本体の setPath() は if (!defined()) ガード付きのため二重定義にならない）。
 */
define('PLUGIN_DIR', '/extension/plugins/');
define('PLUGIN_LIB_DIR', '/extension/plugins/');
