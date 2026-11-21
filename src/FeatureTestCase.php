<?php

declare(strict_types=1);

namespace Acms\TestingFramework;

use Acms\Services\Cache\CacheType;
use Acms\Services\Database\RestoreStatus;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Session;
use Acms\Services\Http\Kernel\Extension\StageMiddlewareRegistry;
use Acms\Services\Http\Kernel\HttpKernel;
use Acms\Services\Http\Kernel\RequestEnvironment;
use Acms\Services\Http\Kernel\Runtime\GlobalHooks;
use Acms\TestingFramework\Http\CapturesTermination;
use Acms\TestingFramework\Http\TestResponse;
use Acms\TestingFramework\Internal\FakeConstants;
use Acms\TestingFramework\Internal\FakeRuntime;
use Acms\TestingFramework\Internal\FakeSessionEngine;
use Acms\TestingFramework\Internal\FeatureRequest;
use Acms\TestingFramework\Internal\GlobalStateGuard;
use Acms\TestingFramework\Internal\StaleTemporaryFiles;
use Acms\TestingFramework\Seeder\BlogSeeder;
use Acms\TestingFramework\Seeder\CategorySeeder;
use Acms\TestingFramework\Seeder\EntrySeeder;
use Acms\TestingFramework\Seeder\UserSeeder;
use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;

/**
 * アプリ全体に 1 リクエストを通し、戻り値の応答を確かめる機能テストの基底クラス（公開 API）
 *
 * 本物の `HttpKernel`（本番と同じミドルウェアの列）に、同じプロセスで何度でもリクエストを通せる。Laravel の機能テスト（`$this->get()` / `post()` /
 * `actingAs()`）と同じ形で書ける。DB はトランザクションで巻き戻る（`DatabaseTestCase`）。
 *
 * - リクエスト: `get()` / `post()`。ホストは `withHost()`、ヘッダー・Cookie は `withHeaders()` / `withCookies()`（以後のリクエストに付く）。
 *   `post()` は CSRF トークンを自動で付ける（付けない指定もできる）。
 * - ログイン状態: `actingAs($userId)` / `actingAsGuest()`。
 * - 応答: `TestResponse`（`assertStatus()` `assertRedirect()` `assertSee()` `assertJson()` など）。
 * - 関数の奥の終了（`die403()`・`redirect()`・`Response::json()` など）も、`get()` / `post()` の戻り値の応答として受け取れる。
 * - プラグイン: `PluginActivator::activate()` で有効化すると、`get()` / `post()` で、プラグインが `earlyInit()` / `init()` で登録したミドルウェア（`http.middleware`）と
 *   Hook が本番と同じように動く（登録先と Hook の登録は、リクエストごとに新しくなり、リクエストの後に元へ戻る）。Hook は `HOOK_ENABLE` に従う。
 * - テーマ: 既定は最小のテスト用テーマ。自分のテンプレートは `useTemplates()` で足す。
 * - データ: `seedBlog()` `seedUser()` `seedCategory()` `seedEntry()`（Seeder を包んだもの）。準備は `setUpSite()` に書く。
 * - 後始末: `$_SERVER`・`$GLOBALS`・コンフィグ・ログインなどの状態は、tearDown で必ず元へ戻す。サブクラスで `tearDown()` を上書きするときは
 *   `parent::tearDown()` を呼ぶこと。
 *
 * 公開の約束: `final protected` のメソッドのうち、`@internal` と書いたもの以外が公開 API。`@internal` のもの・`Acms\TestingFramework\Internal\` の
 * クラスは、本体のテストのための部品で、予告なく変わる。プラグインのテストから直接使わない。
 *
 * このクラスは `HttpKernel`（a-blog cms 3.3 系）が必要。描画（RenderTemplate）を通る経路も、本番と同じ経路で通す。
 *
 * Why not trait にする: DatabaseTestCase を継承する必要があり（trait では `setUpDatabase()` / `tearDown()` を土台が先に通せない）、
 * 抽象クラスなら、利用側の準備は `setUpSite()` に書かせて、土台の後始末を飛ばせないようにできる。
 */
abstract class FeatureTestCase extends DatabaseTestCase
{
    use CapturesTermination;

    /**
     * @internal `actingAs()` が入れる CSRF トークン（POST の `formToken` にこの値を入れると、検証を通る）
     */
    protected const CSRF_TOKEN = 'feature-test-csrf-token';

    /**
     * 相対パスで指定したリクエストのホストの既定
     */
    private const DEFAULT_HOST = 'example.test';

    /**
     * カーネルが読む「環境の定数」のうち、リクエストに依らないもの。テストプロセスの起動時の値（なければ既定値）を、リクエストごとの偽物へ写す
     *
     * 本番では config.server.php・ライセンスが定義する。偽物は定義済みの定数を持たないため、読まれるものを先に用意する。
     * リクエストごとに変わるもの（`REQUEST_URL`・`HTTPS`・`UA` など）は、`RequestEnvironment` がリクエストの `$_SERVER` から作る。
     */
    private const ENVIRONMENT_CONSTANTS = [
        'DOMAIN' => 'example.test',
        'DOMAIN_BASE' => '',
        'COOKIE_SECURE' => false,
        'DEBUG_MODE' => 0,
        'BENCHMARK_MODE' => 0,
        'REGEX_EXCEPTION_UA' => '',
        'ARCHIVES_CACHE_SERVER' => '',
        'ONLY_BUILD_LAYOUT' => false,
        'SESSION_NAME' => 'acms_feature_session',
        // ライセンス（VerifyLicense が読む）のうち、テスト基盤の Bootstrap が定義しないもの。開発ライセンスとして扱う
        'LICENSE_DEVELOPMENT' => true,
    ];

    /**
     * リクエストごとの偽物へ写すライセンスの定数の名前
     *
     * 値はテスト基盤の Bootstrap が、制限のない開発用の値で 1 度だけ定義する（ここに既定値は持たない。持っても、先に定義された
     * Bootstrap の値が使われるため、実際の値と食い違って読む人を誤らせる）。偽物は定義済みの定数を持たないので、名前だけを列挙して写す。
     */
    private const LICENSE_CONSTANT_NAMES = [
        'LICENSE_BLOG_LIMIT',
        'LICENSE_EXPIRE',
        'LICENSE_DOMAIN',
        'LICENSE_OPTION_OWNDOMAIN',
        'LICENSE_OPTION_PLUSDOMAIN',
        'LICENSE_OPTION_OEM',
    ];

    /**
     * カーネルへ渡す PHP の実行方式。CLI 以外にしないと、HTTPS への転送・Basic 認証・gzip が何もしない（HttpKernel::create() の `$sapi`）
     */
    private const SAPI = 'apache2handler';

    /**
     * 土台が通す関門が読むコンフィグの既定。リクエストを流す前に、これで上書きする
     *
     * 他のテストがコンフィグを戻さずに終わっても（例: `setConfig('theme', 削除済みの一時テーマ)`）、このテストの結果が変わらないようにする。
     * Basic 認証・ルールは無しにする。テーマは、このあと `installTheme()` が一時テーマへ向ける。
     * テスト内で変えたい値は、`setUpSite()` や各テストで `\Config::set()` する（GlobalStateGuard が tearDown で戻す）。
     */
    private const CONFIG_DEFAULTS = [
        'theme' => 'system',
        'tpl_twig' => 'disabled',
        'basic_auth_user' => [],
        'basic_auth_pass' => [],
        'basic_auth_skip_hosts' => [],
        'directory_index' => [],
        'html_format_validate' => 'off',
        'cache' => 'off',
    ];

    private ?GlobalStateGuard $guard = null;

    /**
     * リクエストの Cookie（セッション ID）に対応する、このテストの間のセッションの偽物（セッション ID => 偽物）
     *
     * @var array<string, FakeSessionEngine>
     */
    private array $sessions = [];

    private ?FakeRuntime $lastRuntime = null;

    /**
     * 公開の `get()` / `post()` が入れ替える前の、コンテナの `http.middleware`（tearDown で戻す）
     */
    private ?StageMiddlewareRegistry $originalRegistry = null;

    /**
     * `actingAs()` のセッション（null なら未ログイン）
     */
    private ?FakeSessionEngine $actingSession = null;

    /**
     * ログインしていない POST に付ける、CSRF トークンだけを持つセッション
     */
    private ?FakeSessionEngine $anonymousSession = null;

    private string $host = self::DEFAULT_HOST;

    /**
     * @var array<string, string>
     */
    private array $headers = [];

    /**
     * @var array<string, string>
     */
    private array $cookies = [];

    /**
     * ルートブログの ID（DB に無い環境では、このテストのトランザクションの中で作る）
     *
     * Why not protected: 公開の約束に載せていない値で、サブクラスが読むと、約束なしに依存されて変えられなくなるため。
     */
    private int $rootBlogId = 0;

    /**
     * 復元中の状態ファイル（テストごとの一時ファイル）
     *
     * 開発環境の実ファイル（`cache/restore-status.json`）には触れない。書くと、テスト中は同じ環境の開発サイトが 503 になり、
     * プロセスが途中で落ちるとファイルが残り続ける。カーネル（RestoreLock）へは `HttpKernel::create()` の引数で渡す。
     */
    private ?string $restoreStatusPath = null;

    private ?RestoreStatus $restoreStatus = null;

    /**
     * このテストが置いた一時テーマのディレクトリ（末尾にスラッシュ）
     */
    private string $themeDirectory = '';

    private string $theme = '';

    private int $blogSequence = 0;

    /**
     * このテストが差し替える前の、ページキャッシュの種類の登録
     */
    private ?CacheType $previousPageCacheType = null;

    /**
     * テストデータの準備（DB のトランザクションの中で、テーマ・コンフィグを整えたあとに呼ばれる）
     */
    protected function setUpSite(): void
    {
    }

    /**
     * @internal 土台の準備（ガード・テーマ・ルートブログなど）。final で上書きできない。自分のデータの準備は `setUpSite()` に書く
     */
    final protected function setUpDatabase(): void
    {
        $this->guard = new GlobalStateGuard();
        $this->guard->capture();
        $this->lastRuntime = null;
        $this->sessions = [];
        $this->actingSession = null;
        $this->anonymousSession = null;
        $this->host = self::DEFAULT_HOST;
        $this->headers = [];
        $this->cookies = [];
        StaleTemporaryFiles::remove(sys_get_temp_dir() . '/acms-feature-restore-status-*.json', '/^acms-feature-restore-status-[0-9a-f]{16}\.json$/');
        $this->restoreStatusPath = sys_get_temp_dir() . '/acms-feature-restore-status-' . bin2hex(random_bytes(8)) . '.json';
        $this->restoreStatus = new RestoreStatus($this->restoreStatusPath);
        // ページキャッシュは機能テストの関心事ではない（cache を off にして、ヒットして別の応答になるのを避ける）
        \ACMS_RAM::cacheDelete();
        foreach (self::CONFIG_DEFAULTS as $key => $value) {
            \Config::set($key, $value);
        }
        $this->rootBlogId = $this->ensureRootBlog();
        // ページキャッシュは描画の関心事ではない。phpunit.xml の `CACHE_PAGE_DRIVER=memory` は、タグ対応のキャッシュに使えないドライバーで
        // （ページキャッシュの保存先を作るたびに「ドライバーが見つからない」警告を記録する）、ドライバーを空にして何も保存しない種類へ登録し直す。
        // 登録は tearDown で元へ戻す（コンテナの登録は GlobalStateGuard が戻さないため、残すとほかのテストのページキャッシュの保存先が変わる）
        $cacheTypes = Application::make('cache.types');
        $this->previousPageCacheType = $cacheTypes->get('page');
        $cacheTypes->register(new CacheType('page', tagAware: true, driver: '', environmentOverride: false));
        $this->installTheme();
        $this->blogSequence = 0;
        $this->setUpSite();
    }

    protected function tearDown(): void
    {
        // setUpDatabase() が途中で失敗しても、元の失敗を隠さないよう、作れたものだけ戻す
        if ($this->previousPageCacheType !== null) {
            Application::make('cache.types')->register($this->previousPageCacheType);
            $this->previousPageCacheType = null;
        }
        $this->removeTheme();
        $this->removeRestoreStatusFile();
        $this->guard?->restore();
        // ガードがコンテナの定義を控えたときの状態へ戻したあとに、最初に使われていた登録先のインスタンスそのものを入れ直す
        // （先に入れ直すと、ガードが定義を戻すときに、そのインスタンスを捨てて作り直してしまう）
        if ($this->originalRegistry !== null) {
            \App::getInstance()->instance('http.middleware', $this->originalRegistry);
            $this->originalRegistry = null;
        }
        parent::tearDown();
    }

    /**
     * 以後のリクエストのホスト（相対パスで指定したとき）。ブログはドメインで解決される
     */
    final protected function withHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    /**
     * 以後のすべてのリクエストに付けるヘッダー（同じ名前は置き換え）
     *
     * @param array<string, string> $headers
     */
    final protected function withHeaders(array $headers): static
    {
        $this->headers = $headers + $this->headers;

        return $this;
    }

    /**
     * 以後のすべてのリクエストに付ける Cookie（同じ名前は置き換え）
     *
     * @param array<string, string> $cookies
     */
    final protected function withCookies(array $cookies): static
    {
        $this->cookies = $cookies + $this->cookies;

        return $this;
    }

    /**
     * 以後のリクエストを、このユーザーでログインした状態にする（セッションと CSRF トークンを用意する）
     */
    final protected function actingAs(int $userId): static
    {
        $this->actingSession = $this->createLoggedInSession($userId);

        return $this;
    }

    /**
     * 以後のリクエストを、未ログインの状態に戻す
     */
    final protected function actingAsGuest(): static
    {
        $this->actingSession = null;

        return $this;
    }

    /**
     * GET のリクエストを送り、応答を返す
     *
     * @param string $path パス（`/news/`）か絶対 URL（`https://a.example.test/news/`）。パスのときのホストは `withHost()`
     * @param array<string, mixed> $query クエリ文字列
     * @param array<string, string> $headers このリクエストだけのヘッダー
     */
    final protected function get(string $path, array $query = [], array $headers = []): TestResponse
    {
        $request = FeatureRequest::get($this->url($path, $query));

        return new TestResponse($this->sendThroughKernel($request = $this->decorate($request, $headers, false), [], null, true), $request->host);
    }

    /**
     * POST のリクエストを送り、応答を返す
     *
     * ハンドラは `ACMS_POST_` を付けたキーで指定する（例: `['ACMS_POST_Foo' => 'on', ...]`）。
     * `$csrf` が true なら、CSRF トークン（`formToken`）を自動で付ける。値を自分で渡したときは、それを使う。
     *
     * @param array<string, mixed> $form フォームの値
     * @param array<string, string> $headers このリクエストだけのヘッダー
     * @param bool $csrf CSRF トークンを自動で付けるか（false で、トークン無しの POST を試せる）
     */
    final protected function post(string $path, array $form = [], array $headers = [], bool $csrf = true): TestResponse
    {
        $request = FeatureRequest::post($this->url($path), $form);

        return new TestResponse($this->sendThroughKernel($request = $this->decorate($request, $headers, $csrf), [], null, true), $request->host);
    }

    /**
     * 自分のテンプレートを、テスト用のテーマへ足す（同じ名前のファイルは置き換える）
     *
     * `$directory` の中身（サブディレクトリを含む）を、テストごとの一時テーマへコピーする。ページに使うテンプレートは、コンフィグ
     * （`\Config::set('tpl_index', 'index.html')` など）で指す。
     */
    final protected function useTemplates(string $directory): void
    {
        $this->copyDirectory(rtrim($directory, '/') . '/', $this->themeDirectory);
    }

    /**
     * テスト用のテーマの名前
     */
    final protected function themeName(): string
    {
        return $this->theme;
    }

    /**
     * ブログを作る（ドメインで解決される、ルートブログの子）
     *
     * @param array<string, mixed> $data BlogSeeder に渡す上書き
     */
    final protected function seedBlog(string $host, string $name, array $data = []): int
    {
        ++$this->blogSequence;
        $left = 1000 + $this->blogSequence * 10;

        return BlogSeeder::seed($data + [
            'blog_parent' => $this->rootBlogId,
            'blog_code' => '',
            'blog_domain' => $host,
            'blog_name' => $name,
            'blog_left' => $left,
            'blog_right' => $left + 1,
        ]);
    }

    /**
     * @param array<string, mixed> $data UserSeeder に渡す上書き
     * @param string $auth 権限（`administrator` / `editor` / `contributor` / `subscriber`）
     */
    final protected function seedUser(int $blogId, string $auth, array $data = []): int
    {
        return UserSeeder::seed($blogId, $data + ['user_auth' => $auth]);
    }

    /**
     * 最上位のカテゴリーを作る
     */
    final protected function seedCategory(int $blogId, string $code, string $name): int
    {
        return CategorySeeder::seed($blogId, ['category_code' => $code, 'category_name' => $name, 'category_parent' => null]);
    }

    /**
     * エントリーを作る（既定は公開）
     *
     * @param array<string, mixed> $data EntrySeeder に渡す上書き（`entry_status` など）
     */
    final protected function seedEntry(int $blogId, int $userId, ?int $categoryId, string $code, string $title, array $data = []): int
    {
        return EntrySeeder::seed($blogId, $userId, $categoryId, $data + ['entry_code' => $code, 'entry_title' => $title, 'entry_status' => 'open']);
    }

    /**
     * @internal 本体のテスト用。リクエストを 1 回カーネルに通し、戻り値の応答を返す
     *
     * 同じテストの中で何度でも呼べる（呼ぶたびに新しいカーネル・窓口の偽物・RequestState を作る）。
     *
     * @param array<string, mixed> $constants 環境の定数の上書き・追加（`SSL_ENABLE` など）
     * @param (\Closure(StageMiddlewareRegistry): void)|null $configure ミドルウェアの差し込み（`$registry->after('auth.basic', $probe)` など）。
     *     プラグインと同じ登録先を、このリクエストのためだけに作る
     */
    final protected function dispatch(FeatureRequest $request, array $constants = [], ?\Closure $configure = null): ResponseInterface
    {
        return $this->sendThroughKernel($request, $constants, $configure, false);
    }

    /**
     * リクエストをカーネルに通す
     *
     * @param bool $withPlugins true なら、プラグインが載る本番に近い構成で通す（公開の `get()` / `post()` の経路）。false は本体のテストの経路
     *   （記録用の偽物の Hook・空の登録先で、Hook の発火と定数の定義を記録して確かめる）。
     *   - 登録先: コンテナの `http.middleware` を、リクエストごとに新しい `StageMiddlewareRegistry` へ入れ替える。プラグインが `earlyInit()` / `init()` で
     *     登録したミドルウェアが動く。本番は 1 リクエスト 1 登録先で、登録先は封印（実行に入ったミドルウェアへの登録は誤り）と `defineStages()` が
     *     1 度きりの前提のため、リクエストをまたいで使い回さない。デバッグモード相当にして、誤った登録は例外にする。
     *   - Hook: 本番と同じ `GlobalHooks`（`HOOK_ENABLE` に従う）で `ACMS_Hook` を発火する。プラグインの Hook が動く。
     *   Why not 常に本物にする: 本体のテストは、Hook の発火の順序と引数・定義した定数を記録した偽物で確かめており、本物にすると確かめられない。
     */
    private function sendThroughKernel(FeatureRequest $request, array $constants, ?\Closure $configure, bool $withPlugins): ResponseInterface
    {
        // 前のリクエストのログイン（共有のセッション）と関数キャッシュを引き継がない
        $this->guard?->resetSession();
        \ACMS_RAM::cacheDelete();
        $this->prepareGlobals($request);
        $fakeConstants = new FakeConstants(array_merge($this->environmentConstants(), $constants));
        $fake = FakeRuntime::create([], $fakeConstants, $withPlugins ? new GlobalHooks() : null);
        $this->lastRuntime = $fake;

        $registry = $withPlugins ? $this->installMiddlewareRegistry() : new StageMiddlewareRegistry();
        // プラグインの init() が足す登録（InjectTemplate・各レジストリ・コンテナの差し替え）は、リクエストの間だけ有効にする。
        // 本番は 1 リクエスト 1 プロセスで登録も消えるが、ここは同じプロセスで何度も流すため、戻さないと get() のたびに積み上がる
        $registrations = $withPlugins ? $this->guard?->captureRegistries() : null;
        $kernel = HttpKernel::create($registry, $fake->runtime, self::SAPI, $this->restoreStatus);
        // ミドルウェアの列が組まれる前（defineStages() の前）の登録は無視される（HttpKernel を通らない経路のため）。create() のあとに登録する
        if ($configure !== null) {
            $configure($registry);
        }
        $serverRequest = new ServerRequest($request->method, $request->requestUrl(), $this->psrHeaders($request), null, '1.1', $_SERVER);

        // 関数の奥の終了（die403() など）は、本番なら送出して exit する。テストでは窓口が例外で応答を運ぶので、ここで受けて handle() の戻り値と同じに扱う。
        // Why not HttpKernel に分岐を入れる: 本番のカーネルにテスト用の経路を持ち込まないため
        try {
            return $this->handleCapturingTermination(static fn (): ResponseInterface => $kernel->handle($serverRequest));
        } finally {
            $registrations?->restore();
        }
    }

    /**
     * @internal 本体のテスト用。最後に dispatch() したときの窓口の偽物（定義された定数・発火した Hook・ステータスを確かめる）
     */
    final protected function lastRuntime(): FakeRuntime
    {
        if ($this->lastRuntime === null) {
            throw new \LogicException('リクエストを送っていません');
        }

        return $this->lastRuntime;
    }

    /**
     * @internal 本体のテスト用。復元（バックアップのインポート）の状態ファイル（テストごとの一時ファイル）
     */
    final protected function restoreStatus(): RestoreStatus
    {
        if ($this->restoreStatus === null) {
            throw new \LogicException('setUpDatabase() より前に復元の状態を扱えません');
        }

        return $this->restoreStatus;
    }

    /**
     * @internal 本体のテスト用。メモリ上のセッションを作り、そのセッション ID の Cookie を持つリクエストだけがこのセッションを使うようにする
     *
     * @param array<string, mixed> $storage セッションに最初から入れておく値
     */
    final protected function createSession(array $storage = []): FakeSessionEngine
    {
        $session = new FakeSessionEngine(bin2hex(random_bytes(16)), $storage);
        $this->sessions[$session->getSessionId()] = $session;

        return $session;
    }

    /**
     * @internal 本体のテスト用。ログイン済みのセッションを作る（`RestoreSession` が復元するのと同じ値を入れる）
     */
    final protected function createLoggedInSession(int $userId): FakeSessionEngine
    {
        return $this->createSession([ACMS_LOGIN_SESSION_UID => $userId, 'formToken' => self::CSRF_TOKEN, 'formTokenExpireAt' => PHP_INT_MAX]);
    }

    /**
     * @internal 本体のテスト用。リクエストにセッションの Cookie を持たせる
     */
    final protected function withSession(FeatureRequest $request, FakeSessionEngine $session): FeatureRequest
    {
        return $request->withCookies([$this->sessionName() => $session->getSessionId()]);
    }

    /**
     * コンテナの `http.middleware` を、新しい登録先へ入れ替えて返す（元の登録先は、最初の入れ替えのときに控えて tearDown で戻す）
     */
    private function installMiddlewareRegistry(): StageMiddlewareRegistry
    {
        $container = \App::getInstance();
        assert($container instanceof \Acms\Application);
        $this->originalRegistry ??= Application::make('http.middleware');
        $registry = new StageMiddlewareRegistry(true);
        $container->instance('http.middleware', $registry);

        return $registry;
    }

    private function url(string $path, array $query = []): string
    {
        $url = preg_match('#^https?://#', $path) === 1 ? $path : 'http://' . $this->host . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $url;
    }

    /**
     * ヘッダー・Cookie・セッション・CSRF トークンを、リクエストへ付ける
     *
     * @param array<string, string> $headers
     */
    private function decorate(FeatureRequest $request, array $headers, bool $csrf): FeatureRequest
    {
        $server = [];
        foreach ($headers + $this->headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = $value;
        }
        $request = $request->withServer($server)->withCookies($this->cookies);
        $session = $this->actingSession;
        if ($csrf) {
            $session ??= $this->anonymousSession ??= $this->createSession(['formToken' => self::CSRF_TOKEN, 'formTokenExpireAt' => PHP_INT_MAX]);
            $request = $request->withPost($request->post + ['formToken' => self::CSRF_TOKEN]);
        }

        return $session === null ? $request : $this->withSession($request, $session);
    }

    /**
     * リクエストの `$_SERVER` の `HTTP_*` から作る PSR-7 のヘッダー（`HTTP_X_FOO` => `X-Foo`）
     *
     * @return array<string, string>
     */
    private function psrHeaders(FeatureRequest $request): array
    {
        $headers = [];
        foreach ($request->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))))] = $value;
            }
        }

        return $headers;
    }

    private function sessionName(): string
    {
        $name = self::ENVIRONMENT_CONSTANTS['SESSION_NAME'];

        return defined('SESSION_NAME') ? constant('SESSION_NAME') : $name;
    }

    /**
     * リクエストが使うセッション。Cookie が指すセッションがあればそれ、なければ空のセッション（`Session::handle()` が読む）
     */
    private function sessionFor(FeatureRequest $request): FakeSessionEngine
    {
        $sessionId = $request->cookies[$this->sessionName()] ?? null;
        if ($sessionId !== null && isset($this->sessions[$sessionId])) {
            return $this->sessions[$sessionId];
        }

        return new FakeSessionEngine(bin2hex(random_bytes(16)));
    }

    private function ensureRootBlog(): int
    {
        $select = \SQL::newSelect('blog');
        $select->addWhereOpr('blog_parent', 0);
        $select->setLimit(1);
        $row = Database::query($select->get(dsn()), 'row');

        return $row ? (int) $row['blog_id'] : BlogSeeder::seed(['blog_parent' => 0, 'blog_code' => '']);
    }

    private function removeRestoreStatusFile(): void
    {
        if ($this->restoreStatusPath !== null && is_file($this->restoreStatusPath)) {
            unlink($this->restoreStatusPath);
        }
        $this->restoreStatusPath = null;
        $this->restoreStatus = null;
    }

    /**
     * このリクエストの環境の定数（`prepareGlobals()` が `$_SERVER` を作ったあとに呼ぶ）
     *
     * 本番の `Application::setConstant()` と同じ `RequestEnvironment` で、リクエストの `$_SERVER` から作る。
     * `ACMS_POST` は定義しない（`Application::getPostParameter()` が、本番と同じく `$_POST` から決めて定義する）。
     *
     * @return array<string, mixed>
     */
    private function environmentConstants(): array
    {
        $constants = [];
        foreach (self::ENVIRONMENT_CONSTANTS as $name => $default) {
            $constants[$name] = defined($name) ? constant($name) : $default;
        }
        foreach (self::LICENSE_CONSTANT_NAMES as $name) {
            if (defined($name)) {
                $constants[$name] = constant($name);
            }
        }
        // 設置場所（DIR_OFFSET など）・信頼プロキシ・設定ファイルが先に定義した `HTTP_HOST` は使わない。テストの URL はルート設置のリクエストで、ホストはリクエストごとに変わるため
        $environment = RequestEnvironment::fromServer($_SERVER);

        return $constants + $environment + [
            'REQUEST_TIME' => time(),
            'START_TIME' => microtime(true),
            'SSL_ENABLE' => 0,
            'FULLTIME_SSL_ENABLE' => 0,
        ];
    }

    /**
     * `$_SERVER`・`$_GET`・`$_POST`・`$_COOKIE`・Application が持つリクエストごとの値・セッションを、このリクエストのものにする
     */
    private function prepareGlobals(FeatureRequest $request): void
    {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => $request->method,
            'REQUEST_URI' => $request->requestUri(),
            'QUERY_STRING' => $request->queryString,
            'HTTP_HOST' => $request->host,
            'REMOTE_ADDR' => '203.0.113.10',
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ], $request->server);
        unset($_SERVER['HTTPS']);
        if ($request->isHttps()) {
            $_SERVER['HTTPS'] = 'on';
        }
        if (!isset($request->server['PHP_AUTH_USER'])) {
            unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }
        parse_str($request->queryString, $getParameters);
        $_GET = $getParameters;
        $_POST = $request->post;
        $_COOKIE = $request->cookies;

        // Application は URL コンテキスト・GET・POST・Cookie の Field を最初の呼び出しで作って持つため、捨てて、このリクエストから作り直させる
        $application = \App::getInstance();
        assert($application instanceof \Acms\Application);
        $application->resetRequestState();
        // Why not Session::swap(): コンテナの 'session' の登録そのものを差し替えてしまい、戻すには本物の Engine（`session_start()` が走る）を
        // 先に作って控える必要がある。ファサードが持つ解決済みのインスタンスだけを置き換えれば、GlobalStateGuard が `clearInstance()` で戻せる
        (new \ReflectionProperty(Session::class, 'instance'))->setValue(null, $this->sessionFor($request));
    }

    private function installTheme(): void
    {
        // SIGKILL などで残った、以前の一時テーマの残骸を掃除する
        StaleTemporaryFiles::remove(SCRIPT_DIR . THEMES_DIR . 'feature-*', '/^feature-[0-9a-f]{12}$/');
        $name = 'feature-' . bin2hex(random_bytes(6));
        $directory = SCRIPT_DIR . THEMES_DIR . $name . '/';
        if (!mkdir($directory) && !is_dir($directory)) {
            throw new \RuntimeException("一時テーマのディレクトリを作れません: {$directory}");
        }
        $this->themeDirectory = $directory;
        // プロセスが途中で落ちても、実ファイルのテーマの置き場に一時テーマを残さない
        register_shutdown_function(static function () use ($directory): void {
            StaleTemporaryFiles::delete($directory);
        });
        $this->copyDirectory(dirname(__DIR__) . '/resources/themes/feature/', $directory);
        $this->theme = $name;
        $config = [
            'theme' => $name,
            'tpl_top' => '_top.html',
            'tpl_detail' => '_entry.html',
            'tpl_index' => 'index.html',
            'tpl_404' => '404.html',
        ];
        foreach ($config as $key => $value) {
            \Config::set($key, $value);
        }
    }

    /**
     * `$source`（末尾にスラッシュ）の中身を `$destination` へ再帰的にコピーする
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new \InvalidArgumentException("テンプレートのディレクトリがありません: {$source}");
        }
        foreach (scandir($source) ?: [] as $file) { // @phpstan-ignore ternary.shortNotAllowed (scandir() は失敗時 false。空配列で扱う)
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($source . $file)) {
                if (!is_dir($destination . $file) && !mkdir($destination . $file)) {
                    throw new \RuntimeException("ディレクトリを作れません: {$destination}{$file}");
                }
                $this->copyDirectory($source . $file . '/', $destination . $file . '/');
                continue;
            }
            copy($source . $file, $destination . $file);
        }
    }

    private function removeTheme(): void
    {
        if ($this->themeDirectory !== '') {
            StaleTemporaryFiles::delete($this->themeDirectory);
        }
        $this->themeDirectory = '';
        $this->theme = '';
    }
}
