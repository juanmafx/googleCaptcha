<?php

declare(strict_types=1);

namespace SecureContact\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use SecureContact\Contact\EncryptedSubmission;
use SecureContact\Persistence\ConnectionProvider;
use SecureContact\Persistence\PdoAdminRepository;
use SecureContact\Persistence\PdoRateLimiter;
use SecureContact\Persistence\PdoSubmissionRepository;
use SecureContact\Security\FieldCipher;
use SecureContact\Tests\Support\FixedClock;

final class MysqlPersistenceTest extends TestCase
{
    private ConnectionProvider $connections;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $dsn = getenv('TEST_DATABASE_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('TEST_DATABASE_DSN is not set.');
        }

        $this->connections = new ConnectionProvider($dsn, (string) getenv('TEST_DATABASE_USER'), (string) getenv('TEST_DATABASE_PASSWORD'));
        $this->clock = new FixedClock();
        $connection = $this->connections->connection();
        $connection->exec('DROP TABLE IF EXISTS admin_users, contact_submissions, rate_limit_counters');
        $schema = (string) file_get_contents(dirname(__DIR__, 3) . '/db/schema.sql');
        foreach (array_filter(array_map(trim(...), explode(';', $schema))) as $statement) {
            $connection->exec($statement);
        }
    }

    public function testSchemaCreatesExpectedTables(): void
    {
        $statement = $this->connections->connection()->query('SHOW TABLES');
        self::assertNotFalse($statement);
        $tables = $statement->fetchAll(PDO::FETCH_COLUMN);
        sort($tables);

        self::assertSame(['admin_users', 'contact_submissions', 'rate_limit_counters'], $tables);
    }

    public function testBinaryCiphertextRoundTripsAtMaximumFieldSize(): void
    {
        $cipher = new FieldCipher(random_bytes(32));
        $message = str_repeat("\u{1F600}", 5000);
        $encrypted = $cipher->encrypt($message, 'context');
        $repository = new PdoSubmissionRepository($this->connections);
        $repository->save(new EncryptedSubmission('11111111-1111-4111-8111-111111111111', $cipher->encrypt('n', 'context'), $cipher->encrypt('e', 'context'), $encrypted, '2026-01-01 00:00:00'));

        $records = $repository->recent(10, 0);

        self::assertSame(1, $repository->count());
        self::assertSame($message, $cipher->decrypt($records[0]->encryptedMessage, 'context'));
    }

    public function testRecentOrdersNewestFirstAndPaginates(): void
    {
        $repository = new PdoSubmissionRepository($this->connections);
        foreach (['2026-01-01', '2026-03-01', '2026-02-01'] as $index => $date) {
            $repository->save(new EncryptedSubmission(sprintf('00000000-0000-4000-8000-00000000000%d', $index), 'n', 'e', 'm', $date . ' 00:00:00'));
        }

        self::assertSame('2026-03-01 00:00:00', $repository->recent(1, 0)[0]->createdAt);
        self::assertSame('2026-02-01 00:00:00', $repository->recent(1, 1)[0]->createdAt);
    }

    public function testAdminRoundTripAndRehash(): void
    {
        $repository = new PdoAdminRepository($this->connections, $this->clock);
        $secret = random_bytes(73);
        $id = $repository->create('site-admin', 'hash-one', $secret);

        self::assertSame($secret, $repository->findById($id)?->encryptedTotpSecret);
        self::assertSame($id, $repository->findByUsername('site-admin')?->id);
        self::assertNull($repository->findByUsername('SITE-ADMIN'));

        $repository->updatePasswordHash($id, 'hash-two');
        self::assertSame('hash-two', $repository->findById($id)?->passwordHash);
    }

    public function testTotpStepAdvancesOnlyForward(): void
    {
        $repository = new PdoAdminRepository($this->connections, $this->clock);
        $id = $repository->create('site-admin', 'hash', 'secret');

        self::assertTrue($repository->advanceTotpStep($id, 100));
        self::assertFalse($repository->advanceTotpStep($id, 100));
        self::assertFalse($repository->advanceTotpStep($id, 99));
        self::assertTrue($repository->advanceTotpStep($id, 101));
    }

    public function testRateLimiterEnforcesAndResetsWindow(): void
    {
        $limiter = new PdoRateLimiter($this->connections, random_bytes(32), $this->clock);

        self::assertTrue($limiter->allow('contact', '203.0.113.10', 2, 60));
        self::assertTrue($limiter->allow('contact', '203.0.113.10', 2, 60));
        self::assertFalse($limiter->allow('contact', '203.0.113.10', 2, 60));
        self::assertTrue($limiter->allow('contact', '203.0.113.11', 2, 60));

        $this->clock->time += 61;
        self::assertTrue($limiter->allow('contact', '203.0.113.10', 2, 60));
    }

    public function testRateLimiterStoresNoRawAddress(): void
    {
        $limiter = new PdoRateLimiter($this->connections, random_bytes(32), $this->clock);
        $limiter->allow('contact', '203.0.113.10', 2, 60);
        $statement = $this->connections->connection()->query('SELECT counter_key FROM rate_limit_counters');
        self::assertNotFalse($statement);

        self::assertStringNotContainsString('203.0.113.10', (string) $statement->fetchColumn());
    }
}
