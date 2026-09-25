<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * field テーブルへの直接 INSERT を行う Seeder。
 *
 * 本番経路（Common::saveField）はキャッシュ削除・ストレージ操作・ログ出力などの副作用が伴うため、
 * 純粋にテストデータを準備したい用途では本 Seeder を使う。
 *
 * type は 'eid' / 'cid' / 'bid' / 'uid' / 'mid' / 'unit_id' のいずれかを指定する。
 */
class FieldSeeder extends Seeder
{
    /**
     * 単一値のフィールドを 1 件 INSERT する。
     *
     * @param 'eid'|'cid'|'bid'|'uid'|'mid'|'unit_id' $type 紐付け先カラムのサフィックス
     * @param int|string $id 紐付け先 ID（unit_id の場合は string）
     * @param int $blogId
     * @param string $key field_key
     * @param string $value field_value
     * @param array $overrides field_type / field_sort / field_search の上書き値
     * @return void
     */
    public static function seed(
        string $type,
        int|string $id,
        int $blogId,
        string $key,
        string $value,
        array $overrides = []
    ): void {
        $sql = SQL::newInsert('field');
        $sql->addInsert('field_key', $key);
        $sql->addInsert('field_value', $value);
        $sql->addInsert('field_type', self::getOrFake($overrides, 'field_type', 'text'));
        $sql->addInsert('field_sort', self::getOrFake($overrides, 'field_sort', 1));
        $sql->addInsert('field_search', self::getOrFake($overrides, 'field_search', 'on'));
        $sql->addInsert('field_' . $type, $id);
        $sql->addInsert('field_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * 複数値の同一フィールドを連続 INSERT する。
     * field_sort は 1 から自動採番される。
     *
     * @param 'eid'|'cid'|'bid'|'uid'|'mid'|'unit_id' $type
     * @param int|string $id
     * @param int $blogId
     * @param string $key
     * @param list<string> $values
     * @param array $overrides
     * @return void
     */
    public static function seedMany(
        string $type,
        int|string $id,
        int $blogId,
        string $key,
        array $values,
        array $overrides = []
    ): void {
        foreach ($values as $i => $value) {
            $perRowOverrides = $overrides + ['field_sort' => $i + 1];
            self::seed($type, $id, $blogId, $key, $value, $perRowOverrides);
        }
    }

    /**
     * field_rev テーブルに単一値のフィールドを 1 件 INSERT する。
     *
     * @param 'eid'|'cid'|'bid'|'uid'|'mid'|'unit_id' $type 紐付け先カラムのサフィックス
     * @param int|string $id 紐付け先 ID（unit_id の場合は string）
     * @param int $blogId
     * @param int $revisionId field_rev_id
     * @param string $key field_key
     * @param string $value field_value
     * @param array $overrides field_type / field_sort / field_search の上書き値
     * @return void
     */
    public static function seedRev(
        string $type,
        int|string $id,
        int $blogId,
        int $revisionId,
        string $key,
        string $value,
        array $overrides = []
    ): void {
        $sql = SQL::newInsert('field_rev');
        $sql->addInsert('field_key', $key);
        $sql->addInsert('field_value', $value);
        $sql->addInsert('field_type', self::getOrFake($overrides, 'field_type', 'text'));
        $sql->addInsert('field_sort', self::getOrFake($overrides, 'field_sort', 1));
        $sql->addInsert('field_search', self::getOrFake($overrides, 'field_search', 'on'));
        $sql->addInsert('field_' . $type, $id);
        $sql->addInsert('field_blog_id', $blogId);
        $sql->addInsert('field_rev_id', $revisionId);
        Database::query($sql->get(dsn()), 'exec');
    }
}
