<?php

namespace Acms\TestingFramework\Seeder;

use Faker\Factory;
use Faker\Generator;

/**
 * Seederベースクラス
 *
 * PR TIMESのSeederパターンを参考に実装
 * @see https://developers.prtimes.jp/2024/02/19/unit-test-improvement-with-simple-seeder-implementation/
 */
abstract class Seeder
{
    protected static ?Generator $faker = null;

    /**
     * Fakerインスタンスを取得
     */
    protected static function faker(): Generator
    {
        if (self::$faker === null) {
            self::$faker = Factory::create('ja_JP');
        }
        return self::$faker;
    }

    /**
     * デフォルト値を取得（存在しなければFakerで生成）
     */
    protected static function getOrFake(array $data, string $key, mixed $default): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * Seeder が明示的に addInsert していないカラムを $data から補って INSERT に足す。
     *
     * 各 Seeder が既知カラム（getOrFake の既定値・引数由来の FK など）を addInsert し終えた直後に
     * 呼ぶ。既に addInsert 済みのカラム（`$sql->_insert` のキー）はスキップするため、Seeder が
     * 既定値を持つカラムや、引数を正とする FK 列（$blogId から先に addInsert したもの）は $data 側で
     * 二重指定されても上書きされない。Seeder が列挙していないカラム（user_login_anywhere /
     * user_pass_reset など）だけが $data の指定値で追加される。
     *
     * これにより「Seeder が既定値を列挙していないカラムを $data に渡しても黙って無視される」という
     * 従来の落とし穴を、各 Seeder 1 行の追加だけで解消できる。
     *
     * @param \SQL_Insert $sql 既知カラムを addInsert 済みの INSERT ビルダー（ここへ追記する）
     * @param array<string, mixed> $data 呼び出し側の指定値
     */
    protected static function addExtraColumns(\SQL_Insert $sql, array $data): void
    {
        $handled = \is_array($sql->_insert) ? $sql->_insert : [];
        foreach ($data as $key => $value) {
            if (\array_key_exists($key, $handled)) {
                continue;
            }
            $sql->addInsert($key, $value);
        }
    }

    /**
     * UUID v4 相当のランダムな識別子を生成
     */
    protected static function generateUuid(): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6))
        );
    }
}
