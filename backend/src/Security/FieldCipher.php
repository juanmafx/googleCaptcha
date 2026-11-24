<?php

declare(strict_types=1);

namespace SecureContact\Security;

use InvalidArgumentException;

final readonly class FieldCipher
{
    private const string VERSION = "\x01";
    private const int NONCE_BYTES = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

    public function __construct(private string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('Cipher key must be 32 bytes.');
        }
    }

    public function encrypt(string $plaintext, string $context): string
    {
        $nonce = random_bytes(self::NONCE_BYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            self::VERSION . $context,
            $nonce,
            $this->key,
        );

        return self::VERSION . $nonce . $ciphertext;
    }

    public function decrypt(string $payload, string $context): string
    {
        if (strlen($payload) < 1 + self::NONCE_BYTES + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES
            || $payload[0] !== self::VERSION) {
            throw new DecryptionFailed('Unsupported ciphertext.');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($payload, 1 + self::NONCE_BYTES),
            self::VERSION . $context,
            substr($payload, 1, self::NONCE_BYTES),
            $this->key,
        );
        if ($plaintext === false) {
            throw new DecryptionFailed('Ciphertext authentication failed.');
        }

        return $plaintext;
    }
}
