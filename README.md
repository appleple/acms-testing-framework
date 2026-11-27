# ablogcms/testing-framework

a-blog cms のプラグイン向け PHPUnit テスト基盤です。プラグインの `require-dev` に追加すると、
本体コアに触れる処理（Services / Validator / Hook や DB を伴う処理）を、プラグイン単体の
リポジトリでテストできます。

> このリポジトリは a-blog cms 本体リポジトリからの **公開ミラー（read-only）**です。Issue / PR は受け付けていません。

> **最短で始めるなら**、この基盤・PHPStan・CI が設定済みのスケルトンから作るのが早いです。
>
> ```bash
> composer create-project ablogcms/plugin-skeleton my-plugin
> ```
>
> 以下は、既存のプラグインへ手動で組み込む場合の手順です。

## 提供するもの

| クラス / スクリプト | 役割 |
|---|---|
| `Acms\TestingFramework\TestCase` | DB 不要の単体テスト用の基底クラスです。 |
| `Acms\TestingFramework\DatabaseTestCase` | DB を使う統合テスト用の基底クラスです（各テストは自動でロールバックされます）。 |
| `Acms\TestingFramework\FeatureTestCase` | アプリ全体に 1 リクエストを通す機能テスト用の基底クラスです。有効化したプラグインのミドルウェア・Hook も動きます（**3.3 系のテスト基盤から**。3.2 系には入りません。後述）。 |
| `Acms\TestingFramework\Http\TestResponse` | 機能テストの応答を確かめる小さなラッパーです。 |
| `Acms\TestingFramework\Http\CapturesTermination` | `die403()`・`redirect()`・`Response::json()` など、関数の奥の終了を応答として受け取る trait です。 |
| `Acms\TestingFramework\Seeder\*` | テストデータを手軽に作成します（Blog / Category / User / Entry など）。 |
| `Acms\TestingFramework\Helpers\PluginActivator` | プラグインの有効化フラグを操作します。 |
| `bootstrap.php` | PHPUnit の `bootstrap` から直接指せる共有エントリです（プラグイン側で `tests/bootstrap.php` を書かずに済みます）。 |
| `bin/acms-create-database` | テスト用 DB のテーブルを作成します（統合テストの前に一度実行します）。 |

## インストール

プラグインの `composer.json` に追加します。

```jsonc
{
  "require-dev": {
    "ablogcms/testing-framework": "3.2.*"
  },
  "autoload":     { "psr-4": { "Acms\\Plugins\\Foo\\": "src/" } },
  "autoload-dev": { "psr-4": { "Acms\\Plugins\\Foo\\Tests\\": "tests/" } }
}
```

バージョンは、**テストしたい a-blog cms のマイナーバージョンに合わせて**指定してください
（3.2 系なら `3.2.*`、3.3 系なら `3.3.*`）。`^3.2` は 3.3 系まで含めてしまうため使いません。

テスト基盤の挙動を変える変更（`TestCase` が全テストの前後で状態を戻す、`Bootstrap` が `LICENSE_*` を定義する、
`UserSeeder` の既定値を変える、など）は、**CMS のマイナーごとのラインの境目（次のマイナーの最初のリリース）で出し**、
すでに配布した前のライン（3.2 系の `3.2.*`）のパッチでは出しません。既存のプラグインのテストが、パッチの更新だけで
落ちたり警告を出したりしないようにするためです。3.3 系へ上げるときに、下の「基底クラスがテストの前後で戻す状態」を確かめてください。

## セットアップ

### 本体の場所とテスト用 DB を渡す

テスト実行時に、a-blog cms 本体の場所（`ACMS_ROOT`）を `phpunit.xml` の `<env>` で渡します。
`ACMS_ROOT` は環境によって異なるので、ご自身のパスに読み替えてください（Docker で開発している
場合はコンテナ内のパスになります）。

```xml
<php>
  <!-- 例: /path/to/acms（a-blog cms 本体を置いているディレクトリ） -->
  <env name="ACMS_ROOT" value="/path/to/acms"/>
</php>
```

テスト用 DB の接続情報（`ACMS_DB_HOST` / `ACMS_DB_PORT` / `ACMS_DB_NAME` / `ACMS_DB_USER` /
`ACMS_DB_PASS`）は、本体の `.env.testing` か CI の環境変数で渡します。`phpunit.xml` の `<env>`
には書かないでください（`.env.testing` の値が無視されてしまうためです）。指定しない場合は
`127.0.0.1` / `3306` / `db_acms_test` / `root` / `root` が使われます。

### bootstrap（共有エントリを直接指す）

テスト起動処理はこのパッケージに同梱の `bootstrap.php` にまとまっているので、`phpunit.xml` の
`bootstrap` でそれを直接指すだけで済みます。**プラグインごとに `tests/bootstrap.php` を書く必要は
ありません。**

```xml
<phpunit bootstrap="vendor/ablogcms/testing-framework/bootstrap.php">
```

追加の初期化（フィクスチャの読み込み、独自のオートロードパス登録など）が必要な場合だけ、
自前の `tests/bootstrap.php` を用意して共有エントリを require してから拡張します（置き換えでは
なく上乗せです）。

```php
<?php
// tests/bootstrap.php（カスタムが必要なときだけ）
require_once __DIR__ . '/../vendor/ablogcms/testing-framework/bootstrap.php';
// ここに独自の初期化を足す
```

本体に触れない単体テストと、本体を使う統合テストで設定を分けたい場合は、統合テスト用に別の
設定ファイル（例: `phpunit-integration.xml.dist`）と別のディレクトリ（例: `tests/Integration`）を
用意してください。

## テストの書き方

- 純粋なロジック（Services / Validator / Hook など）は単体テスト（`TestCase`）で書きます。
- DB を伴う処理は統合テスト（`DatabaseTestCase`）で書きます。
- `GET` / `POST` ハンドラ本体は直接テストせず、ロジックを Services に切り出して間接的にテストします。ハンドラを含めて「リクエストから応答まで」を確かめたいときは、機能テスト（`FeatureTestCase`、3.3 系）で書きます。

### 単体テスト（`TestCase`）

```php
namespace Acms\Plugins\Foo\Tests\Unit;

use Acms\Plugins\Foo\Services\Foo\Helper;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class HelperTest extends TestCase
{
    #[Test]
    public function 先頭にアットマークを付与する(): void
    {
        $this->assertSame('@appleple', (new Helper())->formatHandle('appleple'));
    }
}
```

### 統合テスト（`DatabaseTestCase`）

`setUpDatabase()` で Seeder を使ってデータを用意します。各テストは自動でロールバックされるので、
実際の DB を汚しません。

```php
namespace Acms\Plugins\Foo\Tests\Integration;

use Acms\Plugins\Foo\Services\Foo\UserMapper;
use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Seeder\UserSeeder;
use PHPUnit\Framework\Attributes\Test;

final class UserMapperTest extends DatabaseTestCase
{
    private int $blogId;

    protected function setUpDatabase(): void
    {
        $this->blogId = $this->createTestBlog(['blog_name' => 'テストブログ']);
    }

    #[Test]
    public function メールで有効ユーザーを特定する(): void
    {
        $userId = UserSeeder::seed($this->blogId, ['user_mail' => 'alice@example.com']);

        $result = (new UserMapper($this->blogId))->resolve('alice@example.com');

        $this->assertSame($userId, $result['userId']);
    }
}
```

`DatabaseTestCase` には `createTestBlog()` / `createTestCategory()` / `createTestUser()` /
`createTestEntry()` や、`insertTestData()` / `fetchTestData()` / `countTestData()` /
`deleteTestData()` などのヘルパーがあります。

### 機能テスト（`FeatureTestCase`）

> `FeatureTestCase` と `CapturesTermination` は、a-blog cms 3.3 系の `HttpKernel` を使うため、**3.3 系のテスト基盤（`3.3.*`）にだけ**含まれます。3.2 系のテスト基盤（`3.2.*`）には入れません。
> 3.2 系を使っている既存のプラグインのテストへの影響はありません（`3.2.*` で固定しているかぎり、これらは配布されず、既存のクラスの挙動も変わりません）。

プラグインの POST モジュール・ページの描画・ログイン状態・プラグイン自身のミドルウェアや Hook など、「リクエストを受けてから応答を返すまで」を、本物の `HttpKernel`（本番と同じミドルウェアの列）に通して確かめます。
Laravel の機能テスト（`$this->get()` / `post()` / `actingAs()`）と同じ形で書けます。DB は各テストの終わりに巻き戻り、グローバルな状態（`$_SERVER`・`$GLOBALS`・コンフィグ・ログイン・Hook の登録・プラグインが足す登録）も基底クラスが必ず戻します。

> **巻き戻るのは、DB とグローバルな状態だけです。** メール送信・外部 HTTP・Webhook・`archives/` `media/` などのストレージ・`cache/` への書き込みは**巻き戻りません**。こうした処理をするプラグインのテストは、`Container::override()` で偽物に差し替えてから使ってください（下の「メールや外部への送信は偽物に差し替える」）。

> この節の例は、すべてこのリポジトリのテスト（`tests/Integration/Readme/` と `tests/Unit/Readme/`）にそのまま写して、実際に流して通ることを確かめています。例を直したときは、テストも直してください。

```php
namespace Acms\Plugins\Foo\Tests\Integration;

use Acms\Services\Facades\Database;
use Acms\TestingFramework\FeatureTestCase;
use Acms\TestingFramework\Helpers\PluginActivator;
use PHPUnit\Framework\Attributes\Test;

final class FooRequestTest extends FeatureTestCase
{
    private int $blogId;
    private int $editorId;

    // テーマ・コンフィグを整えたあと、DB のトランザクションの中で呼ばれます
    protected function setUpSite(): void
    {
        $this->blogId = $this->seedBlog('foo.example.test', 'Foo Blog');   // ドメインで解決されるブログ
        $this->editorId = $this->seedUser($this->blogId, 'editor', ['user_name' => 'Alice']);
        $this->withHost('foo.example.test');                                // 相対パスのときのホスト
    }

    private function blogName(): string
    {
        $select = \SQL::newSelect('blog');
        $select->addSelect('blog_name');
        $select->addWhereOpr('blog_id', $this->blogId);

        return (string) Database::query($select->get(dsn()), 'one');
    }
}
```

以降のテストは、この `FooRequestTest` に足します（`Foo` プラグインに、タイトルを更新する POST モジュール `ACMS_POST_Foo_Update` と、JSON を返す `ACMS_POST_Foo_Search` がある想定です）。

#### プラグインの POST モジュールの 403・リダイレクト・JSON

関数の奥の終了（`die403()`・`redirect()`・`Response::json()` など）は、本番では応答を送出して `exit` しますが、テストでは `get()` / `post()` の**戻り値の応答**として受け取れます。
POST は、`ACMS_POST_` を付けたキーでハンドラを指定します。

```php
#[Test]
public function 権限が無ければ403で何も書き込まれない(): void
{
    // 未ログインの POST。ハンドラの中の die403() が、403 の応答として返る
    $this->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'])->assertForbidden();

    $this->assertSame('Foo Blog', $this->blogName());
}

#[Test]
public function 保存したら一覧へ転送し、タイトルを更新する(): void
{
    $this->actingAs($this->editorId)
        ->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'])
        ->assertRedirect('/foo/');       // 絶対 URL でも、`/` で始まるパスでも指定できる

    $this->assertSame('new', $this->blogName());
}

#[Test]
public function JSONを返す(): void
{
    $this->actingAs($this->editorId)
        ->post('/', ['ACMS_POST_Foo_Search' => 'on', 'q' => 'abc'])
        ->assertOk()
        ->assertJson(['ok' => true, 'items' => ['count' => 1]]);   // 連想配列は部分一致、リストは完全一致
}
```

- `assertRedirect('/foo/')` のようにパスで渡したとき、転送先がリクエストと同じホストであることも確かめます（別のホストへの転送を通さないため）。別のホストへの転送を確かめるときは、絶対 URL で渡してください。
- `assertJson([])` は、本文が空の JSON（`[]` か `{}`）のときだけ通ります。何かのキーを確かめたいときは、そのキーを渡してください。

#### 書き込み系のハンドラには、CSRF トークンが無い POST の負のテストを必ず書く

`post()` は、CSRF トークン（`formToken`）を**自動で付けます**。このため、トークンを付けた正常系のテストだけでは、ハンドラの CSRF 検証が外れていても気づけません。
データを書き換える（DB・ファイル・ログイン状態を変える）ハンドラは、必ず `post(..., csrf: false)`（第 4 引数 `false`）の負のテストを書き、**副作用が起きていないこと**（DB の行が変わっていない・ログインが残っているなど）を確かめてください。
応答の本文に何かが出ていないことだけでは、処理が動いたかどうかを確かめたことになりません。

```php
#[Test]
public function トークンが無いPOSTは処理されず何も書き込まれない(): void
{
    // 第 4 引数 false で CSRF トークンを付けない。付ける値を変えるときは、フォームに `formToken` を渡す
    $this->actingAs($this->editorId)
        ->post('/', ['ACMS_POST_Foo_Update' => 'on', 'title' => 'new'], [], false);

    $this->assertSame('Foo Blog', $this->blogName());
}
```

#### プラグインのページの描画・ログイン状態

自分のテンプレートは `useTemplates()` でテスト用のテーマへ足し、ページに使うテンプレートをコンフィグで指します（既定のテーマは最小のもので、`tpl_top` などを `_top.html` などへ向けてあります）。

```php
#[Test]
public function ログインしているときだけ名前を出す(): void
{
    $this->useTemplates(__DIR__ . '/../templates');     // サブディレクトリごと、テストごとの一時テーマへコピーされる
    \Config::set('tpl_top', 'foo-top.html');

    $this->get('/')->assertSee('user=[]');
    $this->actingAs($this->editorId)->get('/')->assertSee('user=[Alice]');   // 以後のリクエストはログイン中
    $this->actingAsGuest()->get('/')->assertSee('user=[]');                 // 未ログインに戻す
}
```

テストの間、本体の `themes/` の下に一時テーマ（`feature-<乱数>/`）を作ります（テストの終わりに消えます。プロセスが途中で落ちたときの残骸は、次の実行の最初に、作ってから 24 時間以上たったものだけ掃除します）。本体の `themes/` に書き込める権限が必要です。

#### プラグインのミドルウェアと Hook

`PluginActivator::activate()` でプラグインを有効化すると、`get()` / `post()` が通すリクエストで、本番と同じように、そのプラグインの `earlyInit()` / `init()` が実行されます。
`earlyInit()` で `http.middleware` に登録したミドルウェアも、`init()` で登録した Hook（`HOOK_ENABLE` に従う）も、実際に動きます。

```php
#[Test]
public function 有効化したプラグインのミドルウェアとHookが動く(): void
{
    PluginActivator::activate('Foo', $this->blogId);   // Acms\Plugins\Foo\ServiceProvider

    $this->get('/')->assertHeader('X-Foo-Plugin', 'on')->assertSee('<!-- foo-hook -->');
}
```

- ミドルウェアの登録先（`StageMiddlewareRegistry`）は、本番の 1 リクエストと同じく**リクエストごとに新しく**なります（登録がリクエストをまたいで積み上がりません）。テストの終わりに、元の登録先へ戻ります。
- Hook の登録（`ACMS_Hook`）と名前空間の登録（`ACMS_Namespace`）も、**リクエストごとに戻ります**（`get()` / `post()` の後に、リクエストの前の状態へ戻る）。プラグインを無効化した後のリクエストや、有効化していない別ブログへのリクエストに、前のリクエストのプラグインの Hook が残って発火することはありません（本番はブログごとに有効なプラグインだけを読みます）。テストが `get()` の前に自分で `ACMS_Hook::singleton()->attach()` した Hook は、リクエストの前の状態に含まれるため、リクエストをまたいで残り、テストの終わりに戻ります。**公開の `get()` / `post()` の後は、プラグインの Hook がリクエストごとに戻ります**（意図した仕様です）。同じテストの中で `get()` のあとにサービスを直接呼んでも、プラグインの Hook は発火しません。プラグインの Hook を確かめるときは、リクエストを通してください。
- プラグインが `init()` などで足す登録（`InjectTemplate::add()`・`cache.types` / `block-editor.function-tags` / `block-editor.authoring-tags` / `embed.providers` / `background-task.registry` / `background-task.progress` ほか名前が `Registry` で終わるサービス・`Container::bind()` / `singleton()` / `override()` / `extend()`）は、**`get()` / `post()` のリクエスト 1 回の間だけ**有効で、リクエストの後に元へ戻ります（本番は 1 リクエスト 1 プロセスで、登録もプロセスと一緒に消えます。戻さないと、`InjectTemplate::add()` のように重複を除かない登録が、`get()` のたびに積み上がります）。登録の結果は、応答（差し込まれた HTML など）で確かめてください。リクエストの後に `InjectTemplate` などを直接読んでも、空に戻っています。
- 戻せないもの: 入れ子のオブジェクトの中身（レジストリが持つ配列の中のオブジェクトの状態）、名前が `Registry` で終わらないサービスの内部状態、プロセスの外の状態（ファイル・メール・外部 HTTP）。
- テストの中では、ミドルウェアの誤った登録（実行に入ったミドルウェアへの登録など）が**例外になります**（本番は、ログに残すだけで処理を続けます）。誤りを見逃さないための違いです。
- プラグインの ServiceProvider は、`Acms\Plugins\{名前}\ServiceProvider` の規約で、オートロードできる状態にしてください（`PluginActivator::activate()` に FQCN を直接渡すこともできます）。
- 本番は、リクエストごとに変わる定数（`BID`・`SUID`・`CID`・`EID` など）を `define()` しますが、機能テストは**同じプロセスで何度もリクエストを流すため、これらを定義しません**（二度目の `define()` は警告になり、一度目の値が残るため）。プラグインのコードがこれらの定数を直接読むと、機能テストの中では未定義の定数のエラーになるか、正しい値になりません。**定数の代わりに、公開の読み口 `Acms\Services\Http\Context` を使ってください**（下の「文脈の読み口（`Context`）」）。定数を直接読む処理は、本番では今までどおり動きます（機能テストで確かめたいときだけ、読み口へ置き換えます）。

#### メールや外部への送信は偽物に差し替える

メール送信・外部 HTTP・Webhook・ストレージ・`cache/` への書き込みは、DB のように巻き戻りません。テストが実際に外へ送ったり、ファイルを残したりしないよう、そうした処理を持つプラグインは、`Container::override()` で偽物に差し替えてから使います（差し替えは、リクエストの間だけでなく、テストの終わりにも戻ります）。

テスト基盤は、`Bootstrap::boot()` で、コンテナの `mailer` を**何も送らないエンジン**にしてあるので、既定ではメールは外へ出ません（本番には影響しません）。送った内容を確かめるときは、記録する偽物に差し替えます。

```php
#[Test]
public function メールを送るプラグインは偽物に差し替えてから使う(): void
{
    RecordingMailer::$sent = [];
    \App::getInstance()->override('mailer', RecordingMailer::class, ['owner' => 'foo-test']);

    $this->actingAs($this->editorId)->post('/', ['ACMS_POST_Foo_Notify' => 'on'])->assertOk();

    $this->assertSame([['to@example.test']], RecordingMailer::$sent);
}
```

`RecordingMailer` は、`Acms\Services\Mailer\Engine` を継承して `init()` を `NullTransport` にし、`send()` で宛先を記録する自作のクラスです（`tests/Fixtures/Readme/RecordingMailer.php`）。外部 HTTP（`Http` サービス）も、同じ方法で偽物へ差し替えられます。

#### 文脈の読み口（`Context`）

`BID`（ブログ）・`CID`・`EID`・`UID`・`SUID`・`ADMIN` などのリクエストごとの値は、定数の代わりに `Acms\Services\Http\Context` の静的メソッド（`Context::blogId()`・`Context::entryId()`・`Context::view()` など）で読みます。本番は定数と同じ値・同じ型で、機能テストでは、**リクエストごとに**そのリクエストの値になります。

```php
#[Test]
public function 公開の読み口は複数のブログでリクエストごとに正しい値を返す(): void
{
    $otherBlogId = $this->seedBlog('bar.example.test', 'Bar Blog');
    PluginActivator::activate('Foo', $this->blogId);
    PluginActivator::activate('Foo', $otherBlogId);

    $this->get('/')->assertSee('<!-- foo-blog=' . $this->blogId . ' -->');
    $this->withHost('bar.example.test')->get('/')->assertSee('<!-- foo-blog=' . $otherBlogId . ' -->');
}
```

- 読み取りだけの公開 API です（文脈を書き換える入口は公開しません）。メソッド名は変えず、値と型は、メソッドの説明にある同名の定数と同じです。
- ID（`rootBlogId` `blogId` `categoryId` `entryId` `revisionId` `userId` `sessionUserId` `sessionBlogId`）と `admin` は、`defined('BID')` の置き換えの `is*Determined()`（例 `Context::isEntryIdDetermined()`）も使えます。ミドルウェアが値を確定する前に読むと `UndeterminedContextError`（`\Error`）になるので、確定前かもしれない位置（早い段のミドルウェア）では、先に `is*Determined()` で確かめてください。`view` など `is*Determined()` が無い値は、確定前に読みません（確定する位置は次の項目）。
- **`Hook::init()` の扱いは、Hook の登録元で違います。** `init` は、ブログの解決・表示型の解決より前（ミドルウェア `hook.init`）に発火します（本番も同じ）。
  - プラグイン（`ServiceProvider` の `init()` / `earlyInit()` で attach する Hook）の `init()`: 登録が `hook.init` より後（`plugin.load` / `plugin.boot`）のため、本番では呼ばれません。文脈を読む処理は、確定した後に呼ばれる Hook（`beforeGetFire` / `afterGetFire` / `beforeBuild` / `afterBuild` など）に書いてください。
  - `extension/acms` の Hook（`Application::load()` が起動時に attach）の `init()`: 呼ばれますが、HTTP のリクエストでは常に文脈が未確定です。`Context::blogId()` / `view()` を読むと `UndeterminedContextError` になり、`is*Determined()` で分岐しても確定はしない（この時点で常に未確定）ので、文脈は `beforeGetFire` など確定後の Hook で読んでください（`ablogcms/extension/acms/Hook.php` の雛形も同じ書き方です）。
- 公開しているのは、ID・表示型（`view` `tpl`）・クエリ（`page` `limit` `order` `keyword` `tag` `field` `start` `end` `date` `query`）・リクエスト（`requestPath` `requestUrl` `baseUrl` `requestTime` `remoteAddr` `ua` `httpHost` `https` `isPreview`）です。足りない値があれば本体へ要望してください（公開の追加は互換を壊しませんが、公開した名前は変えられません）。
- CLI・cron・setup など、リクエストの文脈が無い実行では、定数をそのまま読みます。
- **リクエスト由来の値は、利用者（攻撃者を含む）が変えられる入力です。** `tpl` `page` `limit` `order` `keyword` `tag` `field` `start` `end` `date` `query` `requestPath` `requestUrl` `ua` `httpHost` `remoteAddr` は、検証せずに SQL・ファイルパス・リダイレクト先・HTML へ使わないでください。`Context` はこれらを検証・加工しません。
  - **エスケープしていない値（3.3 から）**: `ua`・`requestUrl`・`requestPath`・`baseUrl`・`httpHost`・`query` は、HTML エスケープしていない値を返します（以前の `ua`・`requestUrl`・`requestPath`・`baseUrl`・`httpHost` はエスケープ済みでした）。HTML・属性・JavaScript の文字列へ出すときは、呼び出し側が出力時に 1 回だけエスケープしてください（`htmlspecialchars($value, ENT_QUOTES)`。ACMS テンプレートの `{var}` と Twig の `{{ var }}` は自動でエスケープします）。URL として HTTP ヘッダー・`Location`・メール・ログ・JSON へ出すときは、そのまま使えます。**エスケープ済みを前提に HTML へそのまま出していたプラグインは、エスケープを足してください（足さないと XSS になります）。** `htmlspecialchars_decode()` で戻していた処理は不要です（残すと、利用者が送った `&amp;` を `&` に変えます）。`query` は `QUERY_STRING` のままで、サーバーが `QUERY_STRING` を渡さないときだけ `null` です（CLI・cron はブートストラップが空文字を補います）。
  - `httpHost` は Host ヘッダー由来で、クライアントが任意の値を送れます。**信頼しないでください。** 自サイトの URL（パスワード再設定の URL・メールの中のリンク・リダイレクト先）やキャッシュのキーに使わず、`baseUrl` や設定（ブログの URL）から作ります。
  - `httpHost`・`baseUrl`・`requestUrl` のホスト部は、ホスト名に使えない文字（`< > " ' &` ・空白など）を取り除いた値です（エスケープに頼らず、HTML へ出す箇所に危険な文字を持ち込まないため）。`httpHost` は、設定ファイルで固定されていれば設定値になります。
  - `remoteAddr` は、`TRUSTED_PROXY_LIST` に信頼するプロキシだけを列挙した場合に限り、プロキシ越しのクライアント IP になります。未設定だと転送ヘッダーを読まないため、プロキシ配下ではプロキシ自身の IP になります。列挙が広すぎる、または信頼するプロキシが転送ヘッダーを上書きしない構成では偽装されうるので、認可・アクセス制限の唯一の根拠にしません。
  - `date` と `view` は、リクエストの処理中は常に文字列です。文脈が無い実行（CLI・cron・setup）では定数の既定値の `null` になるため、型は `string|null` のままです。
- プラグインの PHPStan（`phpstan/extension.neon`）は、`scanDirectories` に本体の `php` ディレクトリを渡せば、`Context` を型付きで解決します（`Services/` は難読化されません）。

#### 公開の約束

`FeatureTestCase` を継承するプラグインのテストが使ってよいのは、次のものだけです。**これ以外（`@internal` と書かれたもの、`Acms\TestingFramework\Internal\` 配下のクラス）は、本体の内部の部品で、
予告なく変わります。プラグインのテストから直接 `use` したり呼んだりしないでください**（本体の内部を作り替えるたびに、プラグインのテストが壊れるためです）。

| 公開 API | 内容 |
|---|---|
| `FeatureTestCase::setUpSite()` | テストデータの準備を書く（上書きする）。 |
| （公開ではない）`setUpDatabase()` | `final` で上書きできません（`@internal`）。準備は `setUpSite()` に書く。 |
| `get($path, $query = [], $headers = [])` / `post($path, $form = [], $headers = [], $csrf = true)` | リクエストを送り、`TestResponse` を返す。`$path` はパスか絶対 URL。有効化したプラグインのミドルウェア・Hook も動く。 |
| `withHost()` / `withHeaders()` / `withCookies()` | 以後のリクエストのホスト・ヘッダー・Cookie。`withHeaders()` / `withCookies()` は同じ名前を置き換える。 |
| `actingAs($userId)` / `actingAsGuest()` | 以後のリクエストのログイン状態。 |
| `useTemplates($directory)` / `themeName()` | 自分のテンプレートをテスト用のテーマへ足す／テーマの名前。 |
| `seedBlog()` / `seedUser()` / `seedCategory()` / `seedEntry()` | Seeder を包んだデータの作成（`DatabaseTestCase` のヘルパーも使える）。 |
| `TestResponse` | `status()` `header()` `body()` `json()` `psr()`、`assertStatus()` `assertOk()` `assertForbidden()` `assertRedirect()` `assertHeader()` `assertSee()` `assertDontSee()` `assertJson()`。 |
| `CapturesTermination`（trait） | `captureTermination()` / `handleCapturingTermination()`。ハンドラ（`ACMS_POST_*` など）を直接呼ぶ単体テストで、終了を応答として受け取る。`TerminationException` は、終了の応答を運ぶ例外（`\Error`）。 |

- `FeatureTestCase` の `tearDown()` を上書きするときは、必ず `parent::tearDown()` を呼んでください。
- 公開の名前は変えません。**公開メソッドの引数名も変えません**（PHP は名前付き引数で呼べるため、引数名の変更も呼び出し側を壊します）。
- **Cookie は `withCookies()` で渡します。** `withHeaders(['Cookie' => ...])` は `Cookie` ヘッダーを付けるだけで、`$_COOKIE`（アプリが読む値）には反映されません（Cookie の渡し方を 2 通りにせず、`withCookies()` に一本化しています）。
- 終了を握りつぶす `catch (\Throwable)` があると、`CapturesTermination` はテストを失敗させます（本番は `exit` で止まるため、握りつぶせないことの取り違えを防ぐためです）。
- 終了の差し替え口 `Terminator::useHandler()` は、**直接呼ばず、`CapturesTermination`（と `FeatureTestCase`）を使ってください**。本体の内部の差し込み口で、テスト実行中でないときに非 null のハンドラを渡すと例外になります（本番のコードが誤って差し替えると、権限エラーの `die403()` などが `exit` せず、処理が先へ進んでしまうため）。
- **testing-framework は `require-dev` 専用です**。本番の `require` や、配布する zip に入れないでください（プラグインの配布物には `tests/` と `vendor/` の開発依存を含めません）。

### 関数の奥の終了を直接受け取る（`CapturesTermination`）

ハンドラを直接呼ぶ単体テストでは、trait を使います。

```php
namespace Acms\Plugins\Foo\Tests\Unit;

use Acms\TestingFramework\Http\CapturesTermination;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class FooHandlerTest extends TestCase
{
    use CapturesTermination;

    #[Test]
    public function 権限が無ければ403で終わる(): void
    {
        $response = $this->captureTermination(fn () => (new \ACMS_POST_Foo_Update())->post());   // 終了したらその応答、しなかったら null

        $this->assertSame(403, $response?->getStatusCode());
    }
}
```

### 基底クラスがテストの前後で戻す状態

`TestCase`（`DatabaseTestCase` も継承）は、プロセスをまたいで残る共有の状態を、**全テストの `setUp()` / `tearDown()` で**次のように戻します。
実行順（`--order-by=random` を含む）で、無関係なテストの結果が変わらないようにするためです。

| 状態 | 戻し方 |
|------|--------|
| `httpStatusCode()` の static | `200 OK` に戻す |
| `Field_Validation::singleton('post')`（POST の入力の入れ物。値・子・メタ・検証の指定） | 空に戻す（`$_POST` 自体は触らない） |
| `Field::singleton()` の `config`・`get`・`query`・`cookie`・`session`（コンフィグとリクエストの入力の入れ物。子の Field の中身を含む。POST の入力は上の行の `Field_Validation::singleton('post')` で、こちらの `Field::singleton('post')` はリクエストが使わないため対象外） | テストの開始時の値に戻す |
| `DatabaseTestCase` の `field`・`module`・`config` キャッシュ | `tearDown()` で、ROLLBACK の前に捨てる（ロールバックで ID が再利用されたとき、前のテストの値が返らないように。保存先が database でも、DELETE が ROLLBACK で戻りコミットされない） |

- 基底クラスは、コンテナの `mailer` を何も送らないエンジンにします（`Bootstrap::boot()`）。
- サブクラスで `setUp()` / `tearDown()` を上書きするときは、**必ず `parent::setUp()` / `parent::tearDown()` を呼んでください**。呼ばないと上の状態が戻りません。
- `setUpBeforeClass()` で POST の入力へ値を置く書き方は、最初のテストの `setUp()` で空に戻されるため動きません。`setUp()` かテストの本文で置いてください。
- 基底クラスの `setUp()` より前（`setUp()` の先頭で `parent::setUp()` を呼ぶ前）にコンフィグを書くと、その値が「開始時の値」として控えられます。意図して書き換えるのは `parent::setUp()` のあとにしてください。
- `Bootstrap` は `LICENSE_DOMAIN` などの `LICENSE_*` 定数を、制限のない開発用の値で定義します（本番の `Application::loadLicense()` の代わり）。プラグインのテストが自分で `define('LICENSE_...')` すると、定義済みの定数の再定義として警告になります。値を変えたい処理は、定数を直接読まない形で確かめてください。
- `UserSeeder` は、`user_pass` を渡さないとき `user_pass_generation` を現行の方式（`PASSWORD_ALGORITHM_GENERATION`）で入れます（ログインの照合は世代の列で方式を決めるため）。`user_pass` を自分で渡した場合は、その方式をシーダーは知らないので、DB の既定値のままにします。必要なら `user_pass_generation` も一緒に渡してください。

## テストデータの作成（Seeder）

必要な値だけを渡すと、残りは自動で埋めてテストデータを作成します。

```php
$blogId = BlogSeeder::seed(['blog_name' => 'テストブログ']);
$userId = UserSeeder::seed($blogId, ['user_mail' => 'alice@example.com', 'user_auth' => 'administrator']);
CategorySeeder::seed($blogId, [], 5); // 5 件まとめて作成します
```

- 渡したキーは、Seeder が既定値を持つカラムを上書きします。
- Seeder が既定で埋めないカラム（`user_login_anywhere` など）も、キーとして渡せば保存されます
  （存在しないカラム名を渡すとエラーになるので、書き間違いに気づけます）。
- ブログ ID などの引数で渡す値は、同名のキーを渡しても引数の値が優先されます。

## プラグインの有効化（`PluginActivator`）

プラグインの有効/無効に依存するロジックを検証したいときに使います。**プラグイン名**を渡すと、
`app` テーブルに保存される ServiceProvider のクラス名（`Acms\Plugins\{名前}\ServiceProvider`）へ
内部で補完します。

```php
use Acms\TestingFramework\Helpers\PluginActivator;

PluginActivator::activate('Foo', $blogId);
$this->assertTrue(PluginActivator::isActive('Foo', $blogId));
```

ServiceProvider が規約と異なる場所にある場合は、その FQCN を直接渡すこともできます。

操作するのは `app` テーブルの有効化フラグだけです。`DatabaseTestCase` で有効化しても、プラグインの ServiceProvider・Hook は動きません。
有効化したプラグインを実際に動かして確かめるには、機能テスト（`FeatureTestCase`、3.3 系）の `get()` / `post()` と組み合わせます
（上の「プラグインのミドルウェアと Hook」）。

## テストの実行

テストは a-blog cms 本体を含む環境（Docker コンテナなど）の中で実行します。

```bash
composer install                 # 依存のインストール（初回）
vendor/bin/acms-create-database  # テスト用 DB のテーブル作成（初回）
vendor/bin/phpunit               # テスト実行
```

## CI

**コピペで使える完全なワークフローはスケルトン**（`.github/workflows/test.yml` / `bitbucket-pipelines.yml`）
にあります。ここでは考え方だけ示します。

CI では a-blog cms 本体を同梱した Docker イメージ（`appleple/acms:<マイナー>-php<PHP>`）の中で
テストを実行します。ポイントは 3 つです。

- 対応する **PHP バージョンを横断**して回す（必要なら a-blog cms のマイナーも併せる）。イメージ
  タグの `php<PHP>` 部分を切り替えます。
- CI 実行時に `ablogcms/testing-framework` を **テスト対象の a-blog cms マイナーに合わせる**
  （`composer require --dev "ablogcms/testing-framework:<マイナー>.*"`）。
- **phpcs / phpstan は PHP ごとに回さず 1 回だけ**実行します（静的解析は実行時の PHP に依らず、
  対応範囲全体を対象にするためです）。

### GitHub Actions（抜粋）

```yaml
jobs:
  phpunit:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        acms: ["3.2"]                              # 3.3 が出たら足すだけ
        php:  ["8.1", "8.2", "8.3", "8.4", "8.5"]
    env:
      ACMS_IMAGE_TAG: "${{ matrix.acms }}-php${{ matrix.php }}"   # docker-compose.yml の image タグに反映
    steps:
      - uses: actions/checkout@v7
      - run: docker compose up -d --wait
      - run: docker compose exec -T acms bash -lc "cd /workspace && composer require --dev 'ablogcms/testing-framework:${{ matrix.acms }}.*' --no-interaction"
      - run: docker compose exec -T acms bash -lc "cd /workspace && vendor/bin/acms-create-database && vendor/bin/phpunit"
```

### Bitbucket Pipelines（抜粋）

マトリクスが無いので `parallel` と YAML アンカーで PHP を並べます。`image:` のタグ
（`<マイナー>-php<PHP>`）で PHP と a-blog cms を選びます。

```yaml
definitions:
  steps:
    - step: &phpunit-32                   # a-blog cms 3.2 ライン（testing-framework も 3.2.*）
        script:
          - composer require --dev "ablogcms/testing-framework:3.2.*" --no-interaction
          - vendor/bin/acms-create-database
          - vendor/bin/phpunit
pipelines:
  default:
    # 3.3 が出たら &phpunit-33（testing-framework:3.3.*）を足し、3.3-php* イメージの parallel を追加するだけ
    - parallel:
        - step: { <<: *phpunit-32, name: php8.1, image: appleple/acms:3.2-php8.1 }
        - step: { <<: *phpunit-32, name: php8.2, image: appleple/acms:3.2-php8.2 }
        - step: { <<: *phpunit-32, name: php8.3, image: appleple/acms:3.2-php8.3 }
        - step: { <<: *phpunit-32, name: php8.4, image: appleple/acms:3.2-php8.4 }
        - step: { <<: *phpunit-32, name: php8.5, image: appleple/acms:3.2-php8.5 }
```

## PHPStan

プラグインを PHPStan で解析する場合は、`phpstan/phpstan` を `require-dev` に追加し、同梱の設定を
読み込むと、a-blog cms 本体のシンボルを解決した状態で解析できます。`scanDirectories` のパスは
環境に合わせて読み替えてください。

```neon
includes:
  - vendor/ablogcms/testing-framework/phpstan/extension.neon
parameters:
  level: max
  # 本番は PHP 8.1〜8.5 対応。その範囲で解析する（新旧バージョン差の取りこぼしを防ぐ）
  phpVersion:
    min: 80100
    max: 80500
  paths:
    - src
    - tests          # テストも型チェックする（基盤の基底クラスは上の include で解決される）
  scanDirectories:
    - /path/to/php   # a-blog cms 本体（ACMS_ROOT 配下の php ディレクトリ）
```

新規プラグインは最初から `level: max` で始めるのが安全です（後から上げるより差分が小さい）。
本体のレガシー領域に由来する誤検知は、baseline 化ではなく該当行の `// @phpstan-ignore <identifier>`
（理由コメント付き）で局所的に抑制することを推奨します。

extension を include すると、次の「実行時にのみ確定する／動的生成される」シンボルも供給されるので、
プラグイン側で `bootstrapFiles` やスタブを自前で用意する必要はありません。

- 設定・CMS 定数（`PLUGIN_DIR` / `PLUGIN_LIB_DIR` など）
- request-scoped 定数（`SUID` / `CID` / `BID` / `EID` / `RBID` / `REQUEST_TIME` など）。ログイン状態や
  コンテキストで変わるものは `int|null` などの union 型で供給するので、`is_int(SUID)` のような防御的判定が
  「常に false」の誤検知になりません（かつ本当に無効な使い方は引き続き検出されます）。
- 短縮ファサードエイリアス（`DB` / `Auth` / `Config` / `Blog` / `Entry` / `Media` ...）。実行時に
  `class_alias` で作られ静的解析からは見えないため、本体ファサードのサブクラスとして供給します。
  名前空間内のコードからは、クラスは名前空間フォールバックしないため `\DB::query(...)` のように明示参照
  してください（定数・関数はグローバルにフォールバックします）。

## ランタイム依存を `src/vendor` に同梱するプラグイン

配布 zip に依存を同梱するプラグイン（例: `onelogin/php-saml` を `src/vendor/` に入れる）は、
テスト実行側の `vendor/autoload.php` だけでは同梱依存が読まれず `Class ... not found` になります。
PHPUnit と PHPStan のそれぞれで一手当てが必要です。

### PHPUnit: 同梱オートローダはプラグイン側で読み込む

同梱依存の読み込みはプラグイン自身の責務です（基盤の役割はコアの起動まで）。共有 bootstrap は
「上乗せ」できる設計なので、自前の `tests/bootstrap.php` で共有 bootstrap を require してから同梱
オートローダを足します（置き換えではなく上乗せ）。

```php
<?php
// tests/bootstrap.php
require_once __DIR__ . '/../vendor/ablogcms/testing-framework/bootstrap.php';
require_once __DIR__ . '/../src/vendor/autoload.php'; // 同梱依存（onelogin/php-saml 等）
```

`phpunit.xml` の `bootstrap` をこの `tests/bootstrap.php` に向けてください（`ACMS_ROOT` などの
`<env />` は従来どおり効きます）。

### PHPStan: `excludePaths.analyse` で「解析だけ除外・scan は継続」

同梱依存は「シンボルは解決したいが、自前で解析（＝エラー報告）はしたくない」ものです。`paths` に `src` を
含めると `src/vendor` も再帰的に解析対象になるため除外が要りますが、**必ず `analyse:` サブキー**を使います。
`analyseAndScan:` にすると `src/vendor` の**シンボルまで scan から外れ** `unknown class OneLogin\Saml2\*`
になります（foot-gun）。

```neon
includes:
  - vendor/ablogcms/testing-framework/phpstan/extension.neon
parameters:
  level: max
  paths:
    - src
  excludePaths:
    analyse:            # 解析だけ除外。scan は継続するのでシンボルは供給される
      - src/vendor
  scanDirectories:
    - /var/www/html/php  # a-blog cms 本体
```

## 応用: 案件で複数プラグインをまとめてテストする

1 つの案件で複数プラグインを作る場合、**プラグインごとに環境を用意する必要はありません**。案件
リポジトリに **1 つのテスト環境**を置き、全プラグインをまとめてテストできます。a-blog cms 本体
（`ACMS_ROOT`）は同梱 Docker イメージ `appleple/acms` を指せばよく、**本体ソースを案件リポジトリに
入れる必要はありません**。案件リポジトリが「`extension/` ＋ `themes/` ＋ `private/config.system.yaml`
だけを管理し、本体は overlay する」構成でも、この形で成立します。

### ディレクトリ構成（開発実体と本体配置を分ける）

a-blog cms は `extension/plugins/{Name}/` 直下に `ServiceProvider.php` を要求します
（`Acms\Plugins\{Name}\` が `extension/plugins/{Name}/` にマップされる）。テストや `composer.json` を
本体配置に混ぜないため、**開発実体は別ディレクトリに置き、本体には runtime 部分（`src/`）だけを
symlink** します。

```
案件リポジトリ/
├── composer.json                 ← テスト用（基盤を 1 回だけ require-dev ＋ 各プラグインの psr-4）
├── phpunit.xml.dist              ← 全プラグイン共通
├── phpstan.neon                  ← 全プラグイン共通
├── plugins/                      ← 開発実体（git 管理）
│   ├── PluginA/
│   │   ├── src/                  ← runtime（ServiceProvider.php はここ ＝ CMS から見た直下）
│   │   │   ├── ServiceProvider.php
│   │   │   └── Services/ GET/ POST/ ...
│   │   └── tests/{Unit,Integration}/
│   └── PluginB/ ...
├── extension/
│   └── plugins/
│       ├── PluginA -> ../../plugins/PluginA/src   ← src/ を symlink（git 管理）
│       └── PluginB -> ../../plugins/PluginB/src
├── themes/
└── private/config.system.yaml
```

`extension/plugins/{Name}` は `plugins/{Name}/src` を指す symlink にします。a-blog cms の探索
（`scandir` / `is_dir`）もオートロード（composer）も symlink を辿るので、CMS からは `src/` の中身が
プラグイン直下として見え、`tests/` や `composer.json` は `extension/plugins/` に漏れません。git は
symlink をそのまま管理できます（Bitbucket も可）。リンク名・実体ディレクトリ名・名前空間の `{Name}`
は揃えてください。

### `composer.json`（1 つで複数プラグイン）

```jsonc
{
  "require-dev": { "ablogcms/testing-framework": "3.2.*" },
  "autoload": {
    "psr-4": {
      "Acms\\Plugins\\PluginA\\": "plugins/PluginA/src/",
      "Acms\\Plugins\\PluginB\\": "plugins/PluginB/src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Acms\\Plugins\\PluginA\\Tests\\": "plugins/PluginA/tests/",
      "Acms\\Plugins\\PluginB\\Tests\\": "plugins/PluginB/tests/"
    }
  }
}
```

- psr-4 は **`src/`（= symlink 先の実体）** を指します。プラグインを足すたびに 1 行足すだけです。
- 各プラグインは `Acms\Plugins\{Name}\` 規約に揃えてください。

### `phpunit.xml.dist`（全プラグインを一括収集）

```xml
<phpunit bootstrap="vendor/ablogcms/testing-framework/bootstrap.php" colors="true" cacheDirectory=".phpunit.cache">
  <testsuites>
    <testsuite name="plugins">
      <directory>plugins</directory>   <!-- 再帰で各プラグインの tests/ を拾う -->
    </testsuite>
  </testsuites>
  <php>
    <env name="ACMS_ROOT" value="/var/www/html"/>   <!-- コンテナ内の CMS ルート -->
  </php>
</phpunit>
```

単体（`TestCase`）と統合（`DatabaseTestCase`）は混在で一括実行できます（単体は DB に触れません）。
`ACMS_DB_*` は `.env.testing` か CI の環境変数で渡します。

### PHPStan（全プラグイン共通）

```neon
includes:
  - vendor/ablogcms/testing-framework/phpstan/extension.neon
parameters:
  level: max
  paths:
    - plugins
  scanDirectories:
    - /var/www/html/php
```

### 実行（同梱イメージを `ACMS_ROOT` に）

案件の `extension/`・`themes/` を `appleple/acms` イメージへ overlay して、その中で回します。symlink
越しにプラグインが `extension/plugins/` に載るので、統合テストで「プラグインが所定位置にある」状態も
自然に満たせます。

```bash
docker compose up -d
docker compose exec acms bash -lc "composer install && vendor/bin/acms-create-database && vendor/bin/phpunit"
```

### 注意

- **本番デプロイに `tests/` を含めない**（`plugins/*/tests` を除外）。runtime に必要なのは symlink 先の
  `src/` だけです。
- **symlink のチェックアウト**: Windows では `git config core.symlinks true` が必要な場合があります
  （macOS / Linux は既定で可）。
- **バージョンは CMS マイナーに固定**（`3.2.*`。`^3.2` は 3.3 系を拾うので不可）。
