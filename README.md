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
- `GET` / `POST` ハンドラ本体は直接テストせず、ロジックを Services に切り出して間接的にテストします。

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

プラグイン実行時にのみ確定する定数（`PLUGIN_DIR` / `PLUGIN_LIB_DIR`）は extension が供給するので、
プラグイン側で `bootstrapFiles` を用意する必要はありません。
