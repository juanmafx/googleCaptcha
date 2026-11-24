<?php

declare(strict_types=1);

namespace SecureContact\Persistence;

use PDO;
use SecureContact\Security\RateLimiter;
use SecureContact\Support\Clock;

final readonly class PdoRateLimiter implements RateLimiter
{
    private const int RETENTION_SECONDS = 86400;

    public function __construct(
        private ConnectionProvider $connections,
        private string $hashKey,
        private Clock $clock,
    ) {
    }

    public function allow(string $bucket, string $subject, int $limit, int $windowSeconds): bool
    {
        $connection = $this->connections->connection();
        $now = $this->clock->now();
        $threshold = $now - $windowSeconds;
        $counterKey = hash_hmac('sha256', $bucket . "\0" . $subject, $this->hashKey, true);

        $upsert = $connection->prepare(
            'INSERT INTO rate_limit_counters (counter_key, window_started_at, hits) VALUES (:counter_key, :started, 1)'
            . ' ON DUPLICATE KEY UPDATE'
            . ' hits = IF(window_started_at <= :hits_threshold, 1, hits + 1),'
            . ' window_started_at = IF(window_started_at <= :window_threshold, :restarted, window_started_at)',
        );
        $upsert->bindValue(':counter_key', $counterKey, PDO::PARAM_LOB);
        $upsert->bindValue(':started', $now, PDO::PARAM_INT);
        $upsert->bindValue(':hits_threshold', $threshold, PDO::PARAM_INT);
        $upsert->bindValue(':window_threshold', $threshold, PDO::PARAM_INT);
        $upsert->bindValue(':restarted', $now, PDO::PARAM_INT);
        $upsert->execute();

        $select = $connection->prepare('SELECT hits FROM rate_limit_counters WHERE counter_key = :counter_key');
        $select->bindValue(':counter_key', $counterKey, PDO::PARAM_LOB);
        $select->execute();
        $hits = (int) $select->fetchColumn();

        if (random_int(1, 100) === 1) {
            $purge = $connection->prepare('DELETE FROM rate_limit_counters WHERE window_started_at < :cutoff');
            $purge->bindValue(':cutoff', $now - self::RETENTION_SECONDS, PDO::PARAM_INT);
            $purge->execute();
        }

        return $hits <= $limit;
    }
}
