<?php

namespace Acms\TestingFramework;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * 全テストの基底クラス
 *
 * 共通の初期化処理やヘルパーメソッドを定義します。
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * リクエストの入力・コンフィグの共有の入れ物（`Field::singleton()` のキー）。テストの前後で戻す
     *
     * コンフィグ以外は、ミドルウェアが 1 リクエストにつき 1 回だけ書く入れ物で、本番ではプロセスと一緒に消える。テストが書いたまま終わると、
     * 実行順で次のテストの結果が変わる（`acmsLink()` の URL に `?entityType=entry` が混ざる、保存する値に別のテストのキーが混ざる）。
     */
    private const SHARED_FIELDS = ['config', 'get', 'query', 'cookie', 'session'];

    /**
     * テストの開始時の共有の Field（キー => 値・子・メタ）
     *
     * @var array<string, array{array<string, array>, array<string, \Field>, array<string, array>}>|null
     */
    private ?array $fieldSnapshots = null;

    /**
     * テスト前の初期化処理
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetHttpStatus();
        $this->resetPostParameter();
        $this->captureSharedFields();
    }

    /**
     * テスト後のクリーンアップ処理
     */
    protected function tearDown(): void
    {
        $this->resetHttpStatus();
        $this->resetPostParameter();
        $this->restoreSharedFields();
        parent::tearDown();
    }

    /**
     * 現在の HTTP ステータス（`httpStatusCode()` の static）を 200 に戻す
     *
     * 操作ハンドラ・ダウンロード・権限エラーなどはステータスを書き、REST API の Kernel などは `isErrorHttpStatus()` で読む。
     * テストが書いたまま終わると、実行順で次のテストの結果が変わる（REST のルートが 404 になる）。テスト側の後始末に任せず、
     * 全テストの前後で戻す。
     */
    private function resetHttpStatus(): void
    {
        if (\function_exists('httpStatusCode')) {
            \httpStatusCode('200 OK');
        }
    }

    /**
     * テストが `setConfig()` / `Config::set()` で書いたコンフィグや、リクエストの入力の共有の入れ物を、テストの後に開始時の値へ戻すため、開始時の値を控える
     *
     * コンフィグは共有の Field に書かれ、DB のロールバックでは戻らない。書いたまま終わるテストの後ろで、コンフィグを読むだけのテストの結果が
     * 実行順によって変わっていた（`unit_group` が on のまま残り、ユニットの旧来のデータに `group` が混ざるなど）。
     */
    private function captureSharedFields(): void
    {
        if (!\class_exists(\Field::class)) {
            return;
        }
        $this->fieldSnapshots = [];
        foreach (self::SHARED_FIELDS as $key) {
            $field = \Field::singleton($key);
            // 子の Field はオブジェクトなので、配列のコピーだけでは同じ実体を指し続け、子の中身を書き換えると戻らない。子も複製して控える
            $this->fieldSnapshots[$key] = [$field->_aryField, self::cloneChildren($field->_aryChild), $field->_aryMeta];
        }
    }

    /**
     * 子の Field を（孫以降も）複製する
     *
     * Field は複製のための専用メソッドを持たない（`overload($field, true)` はメタを引き継がない）ため、PHP の `clone` で子ごとに複製し、
     * 子が持つ子も同じ手順でたどる。
     *
     * @param array<string, \Field> $children
     * @return array<string, \Field>
     */
    private static function cloneChildren(array $children): array
    {
        $cloned = [];
        foreach ($children as $name => $child) {
            $copy = clone $child;
            $copy->_aryChild = self::cloneChildren($copy->_aryChild);
            $cloned[$name] = $copy;
        }

        return $cloned;
    }

    private function restoreSharedFields(): void
    {
        if ($this->fieldSnapshots === null) {
            return;
        }
        foreach ($this->fieldSnapshots as $key => [$fields, $children, $meta]) {
            $field = \Field::singleton($key);
            // 控えた子をそのまま戻すと、次に戻すときまで同じ実体を共有するため、複製して戻す
            [$field->_aryField, $field->_aryChild, $field->_aryMeta] = [$fields, self::cloneChildren($children), $meta];
        }
        $this->fieldSnapshots = null;
        if (\class_exists(\ACMS_RAM::class)) {
            \ACMS_RAM::cacheDelete();
        }
    }

    /**
     * POST の入力（`Field_Validation::singleton('post')`。値・子・メタ・検証の指定）を空に戻す
     *
     * 操作ハンドラ・Field の抽出のテストはこの共有の入れ物へ値と検証の指定を書く。キーを消し忘れる・検証の指定（required など）を残すと、
     * 実行順で次のテストの検証の結果や抽出するキーが変わる。本番は 1 リクエストにつき 1 回しか書かないため、テストの前後で空に戻す。
     * `$_POST` ではなく、ハンドラが読む共有の入れ物だけを戻す。
     */
    private function resetPostParameter(): void
    {
        if (!\class_exists(\Field_Validation::class)) {
            return;
        }
        $post = \Field_Validation::singleton('post');
        [$post->_aryField, $post->_aryChild, $post->_aryMeta, $post->_aryV, $post->_aryMethod, $post->_aryGroup] = [[], [], [], [], [], []];
    }

    /**
     * ディレクトリを再帰的に削除するヘルパーメソッド
     *
     * @param string $dir 削除するディレクトリのパス
     * @return void
     */
    protected function removeDirectory(string $dir): void
    {
        // is_dir() はシンボリックリンクを辿るため、先にリンクを判定する（リンク先の中身まで消さない）
        if (is_link($dir)) {
            unlink($dir);
            return;
        }
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * 一時ディレクトリを作成するヘルパーメソッド
     *
     * @param string $prefix ディレクトリ名のプレフィックス
     * @return string 作成されたディレクトリのパス
     */
    protected function createTempDirectory(string $prefix = 'acms_test_'): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . uniqid();
        mkdir($dir, 0755, true);
        return $dir;
    }

    /**
     * 一時ファイルを作成するヘルパーメソッド
     *
     * @param string $content ファイルの内容
     * @param string $prefix ファイル名のプレフィックス
     * @param string $suffix ファイル名のサフィックス
     * @return string 作成されたファイルのパス
     */
    protected function createTempFile(string $content = '', string $prefix = 'acms_test_', string $suffix = '.txt'): string
    {
        $file = sys_get_temp_dir() . '/' . $prefix . uniqid() . $suffix;
        file_put_contents($file, $content);
        return $file;
    }
}
