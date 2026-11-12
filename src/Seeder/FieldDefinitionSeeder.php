<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

/**
 * field_set / field_definition テーブルへの直接 INSERT を行う Seeder。
 *
 * 本番経路（FieldDefinition\FieldSetRepository / DefinitionRepository）は正規化・検証を伴うため、
 * 壊れた JSON 列など本番経路では作れない状態を用意したい用途でも本 Seeder を使う。
 * JSON 列（layout / config / validation / conditions）は、配列を渡すと本番と同じく json_encode して投入する。
 */
class FieldDefinitionSeeder extends Seeder
{
    /**
     * フィールドセットを 1 件 INSERT する。
     *
     * @param int $blogId
     * @param array<string, mixed> $data 上書きするカラム（field_set_code / field_set_entity_type 等）
     * @return int 作成された field_set_id
     */
    public static function seedSet(int $blogId, array $data = []): int
    {
        $setId = isset($data['field_set_id'])
            ? (int) $data['field_set_id']
            : (int) Database::query(SQL::nextval('field_set_id', dsn()), 'seq');

        $sql = SQL::newInsert('field_set');
        $sql->addInsert('field_set_id', $setId);
        $sql->addInsert('field_set_status', self::getOrFake($data, 'field_set_status', 'open'));
        $sql->addInsert('field_set_entity_type', self::getOrFake($data, 'field_set_entity_type', 'entry'));
        $sql->addInsert('field_set_code', self::getOrFake($data, 'field_set_code', 'set_' . bin2hex(random_bytes(4))));
        $sql->addInsert('field_set_label', self::getOrFake($data, 'field_set_label', 'Test Set'));
        $sql->addInsert('field_set_description', self::getOrFake($data, 'field_set_description', ''));
        $sql->addInsert('field_set_layout', self::encodeJson($data, 'field_set_layout', ['style' => 'section']));
        $sql->addInsert('field_set_conditions', self::encodeJson($data, 'field_set_conditions', null));
        $sql->addInsert('field_set_sort', self::getOrFake($data, 'field_set_sort', 1));
        $sql->addInsert('field_set_scope', self::getOrFake($data, 'field_set_scope', 'local'));
        $sql->addInsert(
            'field_set_updated_datetime',
            self::getOrFake($data, 'field_set_updated_datetime', date('Y-m-d H:i:s'))
        );
        $sql->addInsert('field_set_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');

        return $setId;
    }

    /**
     * フィールド定義を 1 件 INSERT する。
     *
     * @param int $setId 所属フィールドセット ID
     * @param int $blogId
     * @param array<string, mixed> $data 上書きするカラム（field_definition_key / field_definition_type 等）
     * @return int 作成された field_definition_id
     */
    public static function seed(int $setId, int $blogId, array $data = []): int
    {
        $definitionId = isset($data['field_definition_id'])
            ? (int) $data['field_definition_id']
            : (int) Database::query(SQL::nextval('field_definition_id', dsn()), 'seq');

        $sql = SQL::newInsert('field_definition');
        $sql->addInsert('field_definition_id', $definitionId);
        $sql->addInsert('field_definition_set_id', $setId);
        $sql->addInsert('field_definition_parent_id', $data['field_definition_parent_id'] ?? null);
        $sql->addInsert('field_definition_status', self::getOrFake($data, 'field_definition_status', 'open'));
        $sql->addInsert('field_definition_source', self::getOrFake($data, 'field_definition_source', 'blog'));
        $sql->addInsert('field_definition_entity_type', self::getOrFake($data, 'field_definition_entity_type', 'entry'));
        $sql->addInsert(
            'field_definition_key',
            self::getOrFake($data, 'field_definition_key', 'field_' . bin2hex(random_bytes(4)))
        );
        $sql->addInsert('field_definition_label', self::getOrFake($data, 'field_definition_label', 'Test Field'));
        $sql->addInsert('field_definition_type', self::getOrFake($data, 'field_definition_type', 'text'));
        $sql->addInsert('field_definition_description', self::getOrFake($data, 'field_definition_description', ''));
        $sql->addInsert('field_definition_config', self::encodeJson($data, 'field_definition_config', []));
        $sql->addInsert(
            'field_definition_validation',
            self::encodeJson($data, 'field_definition_validation', ['required' => 'none', 'rules' => []])
        );
        $sql->addInsert('field_definition_conditions', self::encodeJson($data, 'field_definition_conditions', null));
        $sql->addInsert('field_definition_search', self::getOrFake($data, 'field_definition_search', 'on'));
        $sql->addInsert('field_definition_sort', self::getOrFake($data, 'field_definition_sort', 1));
        $sql->addInsert(
            'field_definition_updated_datetime',
            self::getOrFake($data, 'field_definition_updated_datetime', date('Y-m-d H:i:s'))
        );
        $sql->addInsert('field_definition_blog_id', $blogId);
        Database::query($sql->get(dsn()), 'exec');

        return $definitionId;
    }

    /**
     * 配列は json_encode し、文字列（壊れた JSON を含む）と null はそのまま返す
     *
     * @param array<string, mixed> $data
     * @param string $key
     * @param mixed $default
     * @return string|null
     */
    private static function encodeJson(array $data, string $key, mixed $default): ?string
    {
        $value = array_key_exists($key, $data) ? $data[$key] : $default;
        if ($value === null || is_string($value)) {
            return $value;
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
