<?php

declare(strict_types=1);

namespace SecureContact\Security;

final class PasswordHasher
{
    public const int MAX_PASSWORD_BYTES = 1024;

    private ?string $decoyHash = null;

    public function __construct(
        private readonly array $options = ['memory_cost' => 65536, 'time_cost' => 3, 'threads' => 1],
    ) {
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        return strlen($password) <= self::MAX_PASSWORD_BYTES && password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }

    public function burnVerification(string $password): void
    {
        $this->decoyHash ??= $this->hash(bin2hex(random_bytes(16)));
        password_verify(substr($password, 0, self::MAX_PASSWORD_BYTES), $this->decoyHash);
    }
}
