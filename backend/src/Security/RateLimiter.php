<?php

declare(strict_types=1);

namespace SecureContact\Security;

interface RateLimiter
{
    public function allow(string $bucket, string $subject, int $limit, int $windowSeconds): bool;
}
