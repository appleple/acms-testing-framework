<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Internal;

use Acms\Application;
use Acms\Services\Container;
use Acms\Services\Facades\Auth;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\Session;
use Acms\Services\Http\Kernel\CurrentContext;
use Acms\Services\Http\Kernel\RequestContext;
use Acms\TestingFramework\Internal\AssetBinding;
use Acms\TestingFramework\Internal\FakeConstants;

/**
 * 機能テストがカーネルに触らせるグローバル状態を、テストの前に控えて、テストのあとに元へ戻す
 *
 * カーネルは `$_SERVER`・`$GLOBALS`・Application の URL コンテキスト（static）・コンフィグの Field・`httpStatusCode()` の static・
 * Login のファサードなどに書き込む。同じプロセスで何度もリクエストを流すため、戻さないと次のテストの結果が変わる（順序依存になる）。
 * 戻さないもの（意図的）:
 * - `ACMS_RAM` の記録（ブログ・カテゴリー等の行を ID で覚える static）: ID で引くだけで、テストが作る行は ID が重ならない（AUTO_INCREMENT は
 *   ロールバックでも戻らない）。関数キャッシュ（`cacheMethod`）は、コンフィグやブログに依存するため restore() で捨てる。
 * - コンテナのサービスそのもの（Application の `make()` が返すインスタンス）: カーネルは書かない。ただしプラグインが足す登録
 *   （`InjectTemplate`・`Registry` で終わるサービスの状態・コンテナの差し替え）は `RegistryState` が戻す。
 * - Hook の登録（`ACMS_Hook` の集まり）: プラグインを有効化した機能テストでは、プラグインの init() が足すため、リクエスト 1 回ごと（`captureRegistries()`）と restore() で元へ戻す。
 * - PHP のセッション（`session_start()`）: `RestoreSession` は、Cookie にセッション ID があるときだけ開く。機能テストは Cookie を渡さない経路を対象にする。
 *
 * Why not プロセスを分ける（`#[RunInSeparateProcess]`）: 1 クラスの中で複数のリクエストを流して「前のリクエストに影響されない」ことまで確かめるのが目的で、
 * テストごと・クラスごとにプロセスを起こすと遅く、漏れそのものを検出できなくなる。取り消せない状態は、本番のコードをリクエストごとに作り直せるようにして
 * 解消している（文脈が変わると求め直す static、`Application::resetRequestState()`）。
 *
 * @internal 本体の内部に依存するテスト基盤の部品。プラグインのテストからは使わない（予告なく変わる）
 */
final class GlobalStateGuard
{
    /**
     * カーネルのミドルウェアが `$GLOBALS` へ書く（または互換のために置く）キー
     */
    private const GLOBAL_KEYS = [
        'app', 'Q', 'Get', 'Post', 'Cookie', 'Config', 'Hook', 'DB', 'domain', 'ps',
        'maintenanceHttpStatus', 'isMaintenanceRequest', 'Namespace', 'redirectHelper', 'Session',
        'GetQuery', 'contents', 'path', 'extend_section_stack',
    ];

    /**
     * カーネルが中身を書く、`Field::singleton()` の共有の Field（セッション・GET・URL コンテキスト・POST）
     *
     * 同じ参照を返し続けるため、中身（フィールド・子・メタ）を控えて書き戻す。config はコンフィグ専用に別途控える。
     */
    private const FIELD_SINGLETONS = ['session', 'get', 'query', 'post', 'cookie'];

    /**
     * Application が最初の呼び出しで作って保持する、リクエストごとの値（private static）
     */
    private const APPLICATION_STATICS = ['q', 'get', 'post', 'cookie', 'unsafeRequestPath'];

    /**
     * @var array<string, mixed>
     */
    private array $server = [];

    /**
     * @var array<string, mixed>
     */
    private array $superGlobals = [];

    /**
     * @var array<string, array{bool, mixed}> キー => [あったか, 値]
     */
    private array $globals = [];

    /**
     * @var array<string, mixed>
     */
    private array $applicationStatics = [];

    private string $httpStatus = '200 OK';

    /**
     * @var array{array<string, array>, array<string, \Field>, array<string, array>}
     */
    private array $config = [[], [], []];

    /**
     * @var array<string, array{array<string, array>, array<string, \Field>, array<string, array>}>
     */
    private array $fieldSingletons = [];

    /**
     * `Field_Validation::singleton('post')`（`Field::singleton('post')` とは別の共有。Application が POST として持つ方）
     *
     * @var array{array<string, array>, array<string, \Field>, array<string, array>, array, array, array}
     */
    private array $validationPost = [[], [], [], [], [], []];

    /**
     * `ACMS_Namespace` のシングルトンが覚えている名前空間の登録（private `_namespaces`）
     *
     * @var array<int, array{path: string, type: string}>
     */
    private array $namespaces = [];

    /**
     * `ACMS_Hook`（HookFactory）に登録された Hook の集まり（protected `_collection`）。プラグインの ServiceProvider::init() が足す
     *
     * @var array<string, object>
     */
    private array $hooks = [];

    private ?object $login = null;

    private ?RegistryState $registries = null;

    private bool $captured = false;

    public function capture(): void
    {
        $this->server = $_SERVER;
        $this->superGlobals = ['_GET' => $_GET, '_POST' => $_POST, '_COOKIE' => $_COOKIE];
        foreach (self::GLOBAL_KEYS as $key) {
            $this->globals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        foreach (self::APPLICATION_STATICS as $name) {
            $this->applicationStatics[$name] = (new \ReflectionProperty(Application::class, $name))->getValue();
        }
        $this->httpStatus = httpStatusCode();
        $config = \Field::singleton('config');
        $this->config = [$config->_aryField, $config->_aryChild, $config->_aryMeta];
        foreach (self::FIELD_SINGLETONS as $key) {
            $field = \Field::singleton($key);
            $this->fieldSingletons[$key] = [$field->_aryField, $field->_aryChild, $field->_aryMeta];
        }
        $post = \Field_Validation::singleton('post');
        $this->validationPost = [$post->_aryField, $post->_aryChild, $post->_aryMeta, $post->_aryV, $post->_aryMethod, $post->_aryGroup];
        $this->namespaces = self::namespaceProperty()->getValue(\ACMS_Namespace::singleton());
        $this->hooks = self::hookCollectionProperty()->getValue(\ACMS_Hook::singleton());
        $this->login = Login::getInstance();
        $this->registries = $this->captureContainerRegistries();
        $this->captured = true;
    }

    /**
     * プラグインが足す登録（`InjectTemplate`・各レジストリ・コンテナの差し替え）を控える。戻すときは返り値の `restore()` を呼ぶ
     *
     * `capture()` / `restore()` はテスト全体の前後で、これは公開の `get()` / `post()` のリクエスト 1 回の前後で使う
     * （リクエストのたびにプラグインの `init()` が走り、登録が積み上がる・次のリクエストへ Hook が残るのを防ぐ）。コンテナの登録に加えて、
     * Hook と名前空間の登録も戻す。
     */
    public function captureRegistries(): RequestRegistrations
    {
        return new RequestRegistrations(
            $this->captureContainerRegistries(),
            self::hookCollectionProperty()->getValue(\ACMS_Hook::singleton()),
            self::namespaceProperty()->getValue(\ACMS_Namespace::singleton())
        );
    }

    private function captureContainerRegistries(): RegistryState
    {
        $container = \App::getInstance();
        assert($container instanceof Container);

        return RegistryState::capture($container);
    }

    public function restore(): void
    {
        if (!$this->captured) {
            return;
        }
        $this->captured = false;
        $_SERVER = $this->server;
        $_GET = $this->superGlobals['_GET'];
        $_POST = $this->superGlobals['_POST'];
        $_COOKIE = $this->superGlobals['_COOKIE'];
        foreach ($this->globals as $key => [$existed, $value]) {
            unset($GLOBALS[$key]);
            if ($existed) {
                $GLOBALS[$key] = $value;
            }
        }
        foreach ($this->applicationStatics as $name => $value) {
            (new \ReflectionProperty(Application::class, $name))->setValue(null, $value);
        }
        httpStatusCode($this->httpStatus);
        $config = \Field::singleton('config');
        [$config->_aryField, $config->_aryChild, $config->_aryMeta] = $this->config;
        foreach ($this->fieldSingletons as $key => [$fields, $children, $meta]) {
            $field = \Field::singleton($key);
            [$field->_aryField, $field->_aryChild, $field->_aryMeta] = [$fields, $children, $meta];
        }
        $post = \Field_Validation::singleton('post');
        [$post->_aryField, $post->_aryChild, $post->_aryMeta, $post->_aryV, $post->_aryMethod, $post->_aryGroup] = $this->validationPost;
        // カーネルの RegisterNamespaces が足した登録を戻す（残ると後のテストで、実クラスの解決結果が変わる）
        self::namespaceProperty()->setValue(\ACMS_Namespace::singleton(), $this->namespaces);
        // プラグインの init() が足した Hook を戻す（残ると、後のテストでも発火する）
        self::hookCollectionProperty()->setValue(\ACMS_Hook::singleton(), $this->hooks);
        // プラグインの init() が足した登録（InjectTemplate・レジストリ・コンテナの差し替え）を戻す
        $this->registries?->restore();
        $this->registries = null;
        // config() などの関数キャッシュは、書き換えたコンフィグ・ブログの値を覚えているため捨てる
        \ACMS_RAM::cacheDelete();
        if ($this->login !== null) {
            Login::swap($this->login);
        }
        // FeatureTestCase が差し替えたセッションの偽物を捨てる（次の呼び出しでコンテナから本物を解決し直す。ここで本物を作ると session_start() が走るため、控えない）
        Session::clearInstance();
        // リクエストが解決して持った Auth（そのリクエストのログインユーザーで決まる）と、登録したアセットは、あとのテストへ残さない
        Auth::clearInstance();
        $this->resetContextBoundStatics();
        // forgetInstance() では足りない: ほかのテストが Asset::swap() で登録を実体の固定に変えていると、同じ実体が返り続ける
        AssetBinding::restore();
    }

    /**
     * 文脈が変わったら求め直す関数の static（`timemachineMode()`・`requestTime()`・`jsModule()`）を、別の文脈で呼んで初期の値に戻す
     *
     * これらは、文脈が無いとき（`handle()` の外）は直前の値を使う。リクエストの最後の値（タイムマシンの有効など）が残ると、
     * 文脈を持たないほかのテストの権限の判定などが変わる。
     */
    private function resetContextBoundStatics(): void
    {
        $previous = CurrentContext::set(new RequestContext(new FakeConstants(['REQUEST_TIME' => time()])));
        try {
            timemachineMode();
            requestTime();
            jsModule();
        } finally {
            CurrentContext::set($previous);
        }
    }

    /**
     * 共有のセッションの Field を空にする（リクエストごとに新しいセッションで始めるため。前のリクエストのログインが残ると、次の結果が変わる）
     */
    public function resetSession(): void
    {
        $session = \Field::singleton('session');
        [$session->_aryField, $session->_aryChild, $session->_aryMeta] = [[], [], []];
    }

    private static function hookCollectionProperty(): \ReflectionProperty
    {
        return RequestRegistrations::hookCollectionProperty();
    }

    private static function namespaceProperty(): \ReflectionProperty
    {
        return RequestRegistrations::namespaceProperty();
    }
}
