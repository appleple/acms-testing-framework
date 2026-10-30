<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration\Seeder;

use Acms\TestingFramework\DatabaseTestCase;
use Acms\TestingFramework\Seeder\BlogSeeder;
use Acms\TestingFramework\Seeder\UserSeeder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * UserSeeder が $data で渡した任意カラムを永続化することの検証。
 *
 * 従来は Seeder が明示的に列挙したカラムしか INSERT せず、user_login_anywhere /
 * user_pass_reset のような「既定値を持たないカラム」を $data に渡しても黙って無視されていた。
 * テストで前提状態（ログイン境界・リセット待ちなど）を組むには seed 後の手動 UPDATE が必要で、
 * これが利用者のつまずきポイントになっていたため、任意カラムを受け付けるよう改善する。
 */
final class UserSeederTest extends DatabaseTestCase
{
    private int $blogId;

    protected function setUpDatabase(): void
    {
        $this->blogId = BlogSeeder::seed(['blog_name' => 'テストブログ']);
    }

    #[Test]
    #[TestDox('既知カラム以外（user_login_anywhere / user_pass_reset）も $data で指定すれば永続化される')]
    public function persistsArbitraryColumnsFromData(): void
    {
        $userId = UserSeeder::seed($this->blogId, [
            'user_mail' => 'seed@example.com',
            'user_login_anywhere' => 'on',
            'user_pass_reset' => 'reset-token',
        ]);

        $row = $this->fetchTestData('user', ['user_id' => $userId]);
        $this->assertNotNull($row);
        $this->assertSame('on', $row['user_login_anywhere']);
        $this->assertSame('reset-token', $row['user_pass_reset']);
    }

    #[Test]
    #[TestDox('既知カラムは従来どおり $data で上書きできる')]
    public function overridesKnownColumns(): void
    {
        $userId = UserSeeder::seed($this->blogId, [
            'user_mail' => 'known@example.com',
            'user_auth' => 'administrator',
        ]);

        $row = $this->fetchTestData('user', ['user_id' => $userId]);
        $this->assertNotNull($row);
        $this->assertSame('known@example.com', $row['user_mail']);
        $this->assertSame('administrator', $row['user_auth']);
    }

    #[Test]
    #[TestDox('user_blog_id は $blogId 引数を正とし $data 側の指定は無視する')]
    public function blogIdArgumentWinsOverData(): void
    {
        $userId = UserSeeder::seed($this->blogId, [
            'user_blog_id' => $this->blogId + 999,
        ]);

        $row = $this->fetchTestData('user', ['user_id' => $userId]);
        $this->assertNotNull($row);
        $this->assertSame($this->blogId, (int) $row['user_blog_id']);
    }
}
