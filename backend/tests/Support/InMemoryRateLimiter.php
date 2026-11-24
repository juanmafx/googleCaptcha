<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Security\RateLimiter;

final class InMemoryRateLimiter implements RateLimiter
{
    public array $hits = [];

    public function allow(string $bucket, string $subject, int $limit, int $windowSeconds): bool
    {
        $key = $bucket . '|' . $subject;
        $this->hits[$key] = ($this->hits[$key] ?? 0) + 1;

        return $this->hits[$key] <= $limit;
    }
}
