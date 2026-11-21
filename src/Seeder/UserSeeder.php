<?php

namespace Acms\TestingFramework\Seeder;

use SQL;
use Acms\Services\Facades\Database;

class UserSeeder extends Seeder
{
    /**
     * ユーザーデータを作成
     *
     * @param int $blogId 所属するブログID
     * @param array $data オーバーライドするカラム値
     * @param int $rowQuantity 作成する行数
     * @return int 最後に作成されたuser_id
     */
    public static function seed(int $blogId, array $data = [], int $rowQuantity = 1): int
    {
        $faker = self::faker();
        $lastId = 0;

        for ($i = 0; $i < $rowQuantity; $i++) {
            $sql = SQL::newInsert('user');

            $userId = self::getOrFake($data, 'user_id', $faker->unique()->numberBetween(1, 1000000));
            $sql->addInsert('user_id', $userId);
            $sql->addInsert('user_name', self::getOrFake($data, 'user_name', $faker->name));
            $sql->addInsert('user_code', self::getOrFake($data, 'user_code', $faker->unique()->userName));
            $sql->addInsert('user_mail', self::getOrFake($data, 'user_mail', $faker->unique()->email));
            // コストは phpunit.xml.dist の PASSWORD_HASH_COST 環境変数でテスト実行時のみ
            // 下げている（passwordHashGeneration3() 参照）。本番と同じ関数を呼ぶことで
            // 生成方式の乖離を避ける。
            $sql->addInsert('user_pass', self::getOrFake($data, 'user_pass', acmsUserPasswordHash('password')));
            // シーダーが作ったハッシュは現行の方式なので、世代の列も現行に合わせる。世代は列で判定されるため、
            // 埋めないとログインの照合が旧い方式（DB の既定値）で行われ、同じパスワードでも通らない。
            // user_pass を $data で渡したときは、その方式をシーダーは知らないため、DB の既定値のままにする
            // （必要なら $data に user_pass_generation も渡す）。
            if (!array_key_exists('user_pass', $data)) {
                $sql->addInsert('user_pass_generation', self::getOrFake($data, 'user_pass_generation', PASSWORD_ALGORITHM_GENERATION));
            }
            $sql->addInsert('user_auth', self::getOrFake($data, 'user_auth', 'subscriber'));
            $sql->addInsert('user_indexing', self::getOrFake($data, 'user_indexing', 'on'));
            $sql->addInsert('user_status', self::getOrFake($data, 'user_status', 'open'));
            $sql->addInsert('user_login_expire', self::getOrFake($data, 'user_login_expire', '9999-12-31'));
            // user_blog_id は引数を正とする（先に addInsert しておけば $data の同名指定は addExtraColumns で無視される）。
            $sql->addInsert('user_blog_id', $blogId);
            // 既定値を列挙していないカラム（user_login_anywhere / user_pass_reset など）も $data で投入可能にする。
            self::addExtraColumns($sql, $data);

            Database::query($sql->get(dsn()), 'exec');
            $lastId = $userId;
        }

        return $lastId;
    }
}
