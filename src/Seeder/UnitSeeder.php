<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;
use Acms\Services\Unit\Constants\UnitAlign;
use Acms\Services\Unit\Models\BlockEditor;
use Acms\Services\Unit\Models\Code;
use Acms\Services\Unit\Models\Custom;
use Acms\Services\Unit\Models\Embed;
use Acms\Services\Unit\Models\ExImage;
use Acms\Services\Unit\Models\File;
use Acms\Services\Unit\Models\Group;
use Acms\Services\Unit\Models\Html;
use Acms\Services\Unit\Models\Image;
use Acms\Services\Unit\Models\Map;
use Acms\Services\Unit\Models\Markdown;
use Acms\Services\Unit\Models\Media;
use Acms\Services\Unit\Models\Module;
use Acms\Services\Unit\Models\NewPage;
use Acms\Services\Unit\Models\OsMap;
use Acms\Services\Unit\Models\RichEditor;
use Acms\Services\Unit\Models\Table;
use Acms\Services\Unit\Models\Text;
use Acms\Services\Unit\Models\Video;
use Acms\Services\Unit\Models\Wysiwyg;
use Acms\Services\Unit\Models\YouTube;

class UnitSeeder extends Seeder
{
    // -----------------------------------------------
    // コア: column レコード挿入
    // -----------------------------------------------

    /**
     * column テーブルにレコードを挿入し、生成した column_id を返す
     *
     * @param int    $entryId エントリーID
     * @param int    $blogId  ブログID
     * @param string $type    column_type
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return string 生成した column_id
     */
    private static function insertColumn(int $entryId, int $blogId, string $type, array $data = []): string
    {
        return self::insertColumnInternal('column', $entryId, $blogId, $type, $data);
    }

    /**
     * column_rev テーブルにレコードを挿入し、生成した column_id を返す
     *
     * @param int    $entryId エントリーID
     * @param int    $blogId  ブログID
     * @param int    $rvid    リビジョンID
     * @param string $type    column_type
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return string 生成した column_id
     */
    private static function insertColumnRev(int $entryId, int $blogId, int $rvid, string $type, array $data = []): string
    {
        return self::insertColumnInternal('column_rev', $entryId, $blogId, $type, $data, $rvid);
    }

    /**
     * column / column_rev テーブルへの共通挿入処理
     *
     * @param 'column'|'column_rev' $table テーブル名
     * @param int    $entryId エントリーID
     * @param int    $blogId  ブログID
     * @param string $type    column_type
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @param int|null $rvid  リビジョンID（column_rev の場合のみ必須）
     * @return string 生成した column_id
     */
    private static function insertColumnInternal(
        string $table,
        int $entryId,
        int $blogId,
        string $type,
        array $data = [],
        ?int $rvid = null
    ): string {
        $id = self::generateUuid();
        $sql = SQL::newInsert($table);
        $sql->addInsert('column_id', $id);
        $sql->addInsert('column_status', self::getOrFake($data, 'column_status', 'open'));
        $sql->addInsert('column_sort', self::getOrFake($data, 'column_sort', 1));
        $sql->addInsert('column_align', self::getOrFake($data, 'column_align', UnitAlign::CENTER->value));
        $sql->addInsert('column_type', $type);
        $sql->addInsert('column_anker', self::getOrFake($data, 'column_anker', ''));
        $sql->addInsert('column_attr', self::getOrFake($data, 'column_attr', ''));
        $sql->addInsert('column_group', self::getOrFake($data, 'column_group', ''));
        $sql->addInsert('column_size', self::getOrFake($data, 'column_size', ''));
        $sql->addInsert('column_field_1', self::getOrFake($data, 'column_field_1', ''));
        $sql->addInsert('column_field_2', self::getOrFake($data, 'column_field_2', ''));
        $sql->addInsert('column_field_3', self::getOrFake($data, 'column_field_3', ''));
        $sql->addInsert('column_field_4', self::getOrFake($data, 'column_field_4', ''));
        $sql->addInsert('column_field_5', self::getOrFake($data, 'column_field_5', ''));
        $sql->addInsert('column_field_6', self::getOrFake($data, 'column_field_6', ''));
        $sql->addInsert('column_field_7', self::getOrFake($data, 'column_field_7', ''));
        $sql->addInsert('column_field_8', self::getOrFake($data, 'column_field_8', ''));
        $sql->addInsert('column_group_class', self::getOrFake($data, 'column_group_class', ''));
        $sql->addInsert('column_group_tag', self::getOrFake($data, 'column_group_tag', ''));
        $sql->addInsert('column_entry_id', $entryId);
        $sql->addInsert('column_blog_id', $blogId);
        if ($rvid !== null) {
            $sql->addInsert('column_rev_id', $rvid);
        }
        Database::query($sql->get(dsn()), 'exec');
        return $id;
    }

    /**
     * field テーブルにカスタムユニットのフィールドレコードを1件挿入
     *
     * @param string $unitId    カスタムユニットの column_id
     * @param int    $blogId    ブログID
     * @param array{key: string, value: string, type?: string, sort?: int} $fieldData フィールドデータ
     * @param int    $autoSort  fieldData に sort が未指定の場合に使う自動インクリメント値
     */
    private static function insertCustomUnitField(
        string $unitId,
        int $blogId,
        array $fieldData,
        int $autoSort
    ): void {
        $sql = SQL::newInsert('field');
        $sql->addInsert('field_key', $fieldData['key']);
        $sql->addInsert('field_value', $fieldData['value']);
        $sql->addInsert('field_type', $fieldData['type'] ?? 'text');
        $sql->addInsert('field_sort', $fieldData['sort'] ?? $autoSort);
        $sql->addInsert('field_search', 'on');
        $sql->addInsert('field_unit_id', $unitId);
        $sql->addInsert('field_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }

    // -----------------------------------------------
    // テキスト系
    // -----------------------------------------------

    /**
     * テキストユニットを作成
     *
     * column_field_1: テキスト内容
     * column_field_2: テキストタグ（h1, h2, p, div, table など）
     * column_field_3: 拡張タグ属性
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return string 生成した column_id
     */
    public static function seedText(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Text::getUnitType(), array_merge([
            'column_field_1' => 'テキスト',
            'column_field_2' => 'p',
        ], $data));
    }

    /**
     * WYSIWYG ユニットを作成
     *
     * column_field_1: HTML コンテンツ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedWysiwyg(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Wysiwyg::getUnitType(), array_merge([
            'column_field_1' => '<p>テキスト</p>',
        ], $data));
    }

    /**
     * コードユニットを作成
     *
     * column_field_1: コード内容
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedCode(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Code::getUnitType(), array_merge([
            'column_field_1' => '// code',
        ], $data));
    }

    /**
     * Markdown ユニットを作成
     *
     * column_field_1: Markdown コンテンツ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedMarkdown(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Markdown::getUnitType(), array_merge([
            'column_field_1' => '# Markdown',
        ], $data));
    }

    /**
     * HTML ユニットを作成
     *
     * column_field_1: HTML 自由入力コンテンツ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedHtml(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Html::getUnitType(), array_merge([
            'column_field_1' => '<p>HTML</p>',
        ], $data));
    }

    /**
     * リッチエディタユニットを作成
     *
     * column_field_1: リッチエディタ JSON
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedRichEditor(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, RichEditor::getUnitType(), array_merge([
            'column_field_1' => '{}',
        ], $data));
    }

    /**
     * ブロックエディタユニットを作成
     *
     * column_field_1: HTML コンテンツ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedBlockEditor(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, BlockEditor::getUnitType(), array_merge([
            'column_field_1' => '<p>テキスト</p>',
        ], $data));
    }

    /**
     * テーブルユニットを作成
     *
     * column_field_1: テーブルソース（多言語対応）
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedTable(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Table::getUnitType(), $data);
    }

    // -----------------------------------------------
    // メディア系
    // -----------------------------------------------

    /**
     * メディアユニットを作成
     *
     * column_field_1: メディアID（多言語対応）
     * column_field_2: メディアキャプション（多言語対応）
     * column_field_3: メディア Alt 属性（多言語対応）
     * column_field_4: 拡大表示フラグ
     * column_field_5: アイコン使用フラグ
     * column_field_6: 表示サイズ
     * column_field_7: メディアリンク（多言語対応）
     *
     * @param int $entryId
     * @param int $blogId
     * @param int $mediaId 紐付けるメディアID
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedMedia(int $entryId, int $blogId, int $mediaId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Media::getUnitType(), array_merge([
            'column_field_1' => (string) $mediaId,
        ], $data));
    }

    /**
     * 画像ユニットを作成
     *
     * column_field_1: 画像キャプション（多言語対応）
     * column_field_2: 画像ファイルパス（多言語対応）
     * column_field_3: リンク先（多言語対応）
     * column_field_4: 代替テキスト（多言語対応）
     * column_field_5: 表示サイズ
     * column_field_6: EXIF データ（多言語対応）
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedImage(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Image::getUnitType(), $data);
    }

    /**
     * 外部画像ユニットを作成
     *
     * column_field_1: キャプション
     * column_field_2: 通常サイズ画像 URL
     * column_field_3: 大きなサイズ画像 URL
     * column_field_4: リンク先 URL
     * column_field_5: 代替テキスト
     * column_field_6: 表示サイズ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedExImage(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, ExImage::getUnitType(), $data);
    }

    /**
     * ファイルユニットを作成
     *
     * column_field_1: ファイルキャプション（多言語対応）
     * column_field_2: ファイルパス（多言語対応）
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedFile(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, File::getUnitType(), $data);
    }

    /**
     * 動画ユニットを作成
     *
     * column_field_2: ビデオ ID
     * column_field_3: 表示サイズ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedVideo(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Video::getUnitType(), $data);
    }

    /**
     * YouTube ユニットを作成
     *
     * column_field_2: YouTube ビデオ ID
     * column_field_3: 表示サイズ
     *
     * @deprecated YouTube ユニットは非推奨。Video ユニットの使用を推奨。
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedYouTube(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, YouTube::getUnitType(), $data);
    }

    // -----------------------------------------------
    // 埋め込み・地図系
    // -----------------------------------------------

    /**
     * 埋め込みユニット（Quote）を作成
     *
     * column_field_1: サイトプロバイダ名
     * column_field_2: 著者名
     * column_field_3: タイトル
     * column_field_4: 説明
     * column_field_5: 画像（フォールバック）
     * column_field_6: 埋め込み URL
     * column_field_7: カスタム HTML
     * column_field_8: 画像 URL
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedEmbed(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Embed::getUnitType(), $data);
    }

    /**
     * Google マップユニットを作成
     *
     * column_field_1: マップメッセージ（吹き出しテキスト）
     * column_field_2: 緯度
     * column_field_3: 経度
     * column_field_4: ズームレベル
     * column_field_5: 表示サイズ
     * column_field_6: ストリートビューアクティブフラグ
     * column_field_7: ストリートビュー設定（ピッチ, ズーム, ヘディング）
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedMap(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Map::getUnitType(), $data);
    }

    /**
     * 標準マップ（OpenStreetMap）ユニットを作成
     *
     * column_field_1: マップメッセージ
     * column_field_2: 緯度
     * column_field_3: 経度
     * column_field_4: ズームレベル
     * column_field_5: 表示サイズ
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedOsMap(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, OsMap::getUnitType(), $data);
    }

    // -----------------------------------------------
    // 構造系
    // -----------------------------------------------

    /**
     * グループユニットを作成
     *
     * column_group_class: グループのクラス属性
     * column_group_tag:   グループの HTML タグ（div, section, article など）
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedGroup(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Group::getUnitType(), array_merge([
            'column_group_tag' => 'div',
        ], $data));
    }

    /**
     * 改ページユニットを作成
     *
     * column_field_1: 改ページラベル
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedNewPage(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, NewPage::getUnitType(), $data);
    }

    /**
     * モジュールユニットを作成
     *
     * column_field_1: モジュールID
     * column_field_2: モジュールテンプレート
     *
     * @param int $entryId
     * @param int $blogId
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedModule(int $entryId, int $blogId, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, Module::getUnitType(), $data);
    }

    // -----------------------------------------------
    // 動的フォーム (form2)
    // -----------------------------------------------

    /**
     * 動的フォーム（form2）の column 行を作成する。
     *
     * form2 のフォーム項目は column テーブルに column_attr='acms-form' で保存される。
     * column_type は 'text' / 'textarea' / 'radio' / 'select' / 'checkbox' のいずれか。
     *
     * column_field_1: ラベル
     * column_field_2: キャプション
     * column_field_6: 選択肢（radio / select / checkbox の場合の acmsSerialize 値）
     * column_field_7: バリデーションメッセージ
     * column_field_8: バリデーション設定
     *
     * @param int $entryId
     * @param int $blogId
     * @param string $type 'text' / 'textarea' / 'radio' / 'select' / 'checkbox'
     * @param array<string, mixed> $data オーバーライドするカラム値
     * @return string 生成した column_id
     */
    public static function seedFormColumn(int $entryId, int $blogId, string $type, array $data = []): string
    {
        return self::insertColumn($entryId, $blogId, $type, array_merge([
            'column_attr' => 'acms-form',
            'column_field_1' => 'ラベル',
            'column_field_2' => 'キャプション',
        ], $data));
    }

    // -----------------------------------------------
    // リビジョン (column_rev)
    // -----------------------------------------------

    /**
     * column_rev テーブルにテキストユニットを作成
     *
     * @param int $entryId
     * @param int $blogId
     * @param int $rvid リビジョンID
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedTextRev(int $entryId, int $blogId, int $rvid, array $data = []): string
    {
        return self::insertColumnRev($entryId, $blogId, $rvid, Text::getUnitType(), array_merge([
            'column_field_1' => 'テキスト',
            'column_field_2' => 'p',
        ], $data));
    }

    /**
     * column_rev テーブルに画像ユニットを作成
     *
     * @param int $entryId
     * @param int $blogId
     * @param int $rvid リビジョンID
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedImageRev(int $entryId, int $blogId, int $rvid, array $data = []): string
    {
        return self::insertColumnRev($entryId, $blogId, $rvid, Image::getUnitType(), $data);
    }

    /**
     * column_rev テーブルにファイルユニットを作成
     *
     * @param int $entryId
     * @param int $blogId
     * @param int $rvid リビジョンID
     * @param array<string, mixed> $data
     * @return string 生成した column_id
     */
    public static function seedFileRev(int $entryId, int $blogId, int $rvid, array $data = []): string
    {
        return self::insertColumnRev($entryId, $blogId, $rvid, File::getUnitType(), $data);
    }

    // -----------------------------------------------
    // カスタムユニット
    // -----------------------------------------------

    /**
     * カスタムユニットと、ユニット内のフィールドを作成してエントリーと紐付ける
     *
     * カスタムユニットのフィールドは field テーブルに保存される。
     * 各フィールドは以下のキーを持つ配列で指定する:
     * - key   (required) フィールドキー（例: 'title', 'image@media'）
     * - value (required) フィールド値
     * - type  (optional) フィールドタイプ（デフォルト: 'text'）
     * - sort  (optional) ソート順（省略時は配列のインデックスに基づき自動設定）
     *
     * @param int $entryId エントリーID
     * @param int $blogId  ブログID
     * @param array<array{key: string, value: string, type?: string, sort?: int}> $fields カスタムユニットフィールドの配列
     * @param array<string, mixed> $data column テーブルのオーバーライド値
     * @return string 生成した column_id
     */
    public static function seedCustom(
        int $entryId,
        int $blogId,
        array $fields = [],
        array $data = []
    ): string {
        $unitId = self::insertColumn($entryId, $blogId, Custom::getUnitType(), $data);

        foreach ($fields as $i => $fieldData) {
            self::insertCustomUnitField($unitId, $blogId, $fieldData, $i + 1);
        }

        return $unitId;
    }
}
