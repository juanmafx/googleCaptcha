<?php

declare(strict_types=1);

namespace SecureContact\Security;

use InvalidArgumentException;

final readonly class KeyRing
{
    private const string CONTEXT = 'gcaptcha';
    private const int SUBMISSION_KEY_ID = 1;
    private const int TOTP_KEY_ID = 2;
    private const int RATE_LIMIT_KEY_ID = 3;

    public function __construct(private string $masterKey)
    {
        if (strlen($masterKey) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new InvalidArgumentException('Master key must be 32 bytes.');
        }
    }

    public function submissionKey(): string
    {
        return $this->derive(self::SUBMISSION_KEY_ID);
    }

    public function totpKey(): string
    {
        return $this->derive(self::TOTP_KEY_ID);
    }

    public function rateLimitKey(): string
    {
        return $this->derive(self::RATE_LIMIT_KEY_ID);
    }

    private function derive(int $id): string
    {
        return sodium_crypto_kdf_derive_from_key(32, $id, self::CONTEXT, $this->masterKey);
    }
}
