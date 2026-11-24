<?php

declare(strict_types=1);

namespace SecureContact\Security;

final class PasswordHasher
{
    public const int MAX_PASSWORD_BYTES = 1024;
    private const string DECOY_HASH = '$argon2id$v=19$m=65536,t=3,p=1$Zi5XQlNQRVVWc05lbDF5UQ$AdjGntuHlicDg80j3j0eocGiA8LJTRxhVEjGleQWXYI';

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
        if (strlen($password) > self::MAX_PASSWORD_BYTES) {
            $this->burnVerification($password);

            return false;
        }

        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }

    public function burnVerification(string $password): void
    {
        password_verify(substr($password, 0, self::MAX_PASSWORD_BYTES), $this->decoy());
    }

    private function decoy(): string
    {
        if (!$this->needsRehash(self::DECOY_HASH)) {
            return self::DECOY_HASH;
        }

        return $this->decoyHash ??= $this->hash(bin2hex(random_bytes(16)));
    }
}
