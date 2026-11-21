<?php

declare(strict_types=1);

namespace Acms\TestingFramework\Tests\Integration;

use Acms\Services\Facades\Database;
use Acms\TestingFramework\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use ReflectionMethod;

/**
 * DatabaseTestCase のトランザクション後始末の検証
 *
 * DDL は暗黙コミットを起こしてトランザクションを閉じるため、tearDown の
 * 「トランザクションが有効なら」という分岐に入らない。この分岐の内側で autocommit を
 * 復元していると autocommit=0 が次のテストへ漏れ、次の setUp が beginTransaction() より
 * 前に実テーブルへ触れた時点で InnoDB が新しいトランザクションを開き、
 * beginTransaction() が「There is already an active transaction」で失敗する。
 * その例外は setUp の catch で markTestSkipped に変換されるため、以降のテストが
 * 静かにスキップされ続ける。
 */
#[CoversClass(DatabaseTestCase::class)]
class DatabaseTestCaseTransactionTest extends DatabaseTestCase
{
    #[Test]
    #[TestDox('DDL で暗黙コミットが起きても tearDown が autocommit を復元する')]
    public function tearDownRestoresAutocommitAfterImplicitCommitByDdl(): void
    {
        // Arrange: DDL を実行して暗黙コミットを起こす（トランザクションが閉じる）
        $table = 'acms_tf_tx_test_' . uniqid();
        Database::query(['sql' => sprintf('CREATE TABLE `%s` (id int NOT NULL) ENGINE=InnoDB', $table), 'params' => []], 'exec');
        Database::query(['sql' => sprintf('DROP TABLE `%s`', $table), 'params' => []], 'exec');
        $this->assertFalse(
            Database::connection()->inTransaction(),
            '事前条件: DDL の暗黙コミットでトランザクションが閉じている'
        );

        // Act: 後始末を実行する（PHPUnit がこの後もう一度呼ぶため冪等である必要がある）
        $tearDown = new ReflectionMethod($this, 'tearDown');
        $tearDown->invoke($this);

        // Assert: 次のテストへ autocommit=0 が漏れていない
        $this->assertSame(
            '1',
            (string) Database::query('SELECT @@autocommit', 'one'),
            'tearDown 後は autocommit が復元されている'
        );
    }

    #[Test]
    #[TestDox('前のテストで DDL を実行しても、次のテストはトランザクション内で開始できる')]
    public function transactionStartsEvenAfterPreviousTestRanDdl(): void
    {
        // 直前のテストが autocommit を漏らしていれば setUp の beginTransaction() が
        // 失敗し、このテストはスキップされる（＝実行されればトランザクションが張れている）
        $this->assertTrue(
            Database::connection()->inTransaction(),
            'setUp がトランザクションを開始できている'
        );
    }
}
