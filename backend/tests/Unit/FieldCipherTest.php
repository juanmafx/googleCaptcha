<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SecureContact\Security\DecryptionFailed;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\KeyRing;

final class FieldCipherTest extends TestCase
{
    public function testRoundTripHidesPlaintext(): void
    {
        $cipher = new FieldCipher(random_bytes(32));
        $payload = $cipher->encrypt('ada@example.com', 'context');

        self::assertStringNotContainsString('ada@example.com', $payload);
        self::assertSame('ada@example.com', $cipher->decrypt($payload, 'context'));
    }

    public function testNoncesMakeCiphertextsUnique(): void
    {
        $cipher = new FieldCipher(random_bytes(32));

        self::assertNotSame($cipher->encrypt('same', 'context'), $cipher->encrypt('same', 'context'));
    }

    public function testRejectsCiphertextMovedToAnotherContext(): void
    {
        $cipher = new FieldCipher(random_bytes(32));
        $payload = $cipher->encrypt('secret', 'submission:a:email');

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt($payload, 'submission:b:email');
    }

    public function testRejectsTamperedCiphertext(): void
    {
        $cipher = new FieldCipher(random_bytes(32));
        $payload = $cipher->encrypt('secret', 'context');
        $payload[strlen($payload) - 1] = chr(ord($payload[strlen($payload) - 1]) ^ 1);

        $this->expectException(DecryptionFailed::class);
        $cipher->decrypt($payload, 'context');
    }

    public function testRejectsWrongKey(): void
    {
        $payload = (new FieldCipher(random_bytes(32)))->encrypt('secret', 'context');

        $this->expectException(DecryptionFailed::class);
        (new FieldCipher(random_bytes(32)))->decrypt($payload, 'context');
    }

    public function testRejectsTruncatedPayload(): void
    {
        $this->expectException(DecryptionFailed::class);
        (new FieldCipher(random_bytes(32)))->decrypt("\x01short", 'context');
    }

    public function testRejectsShortKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FieldCipher('short');
    }

    public function testKeyRingDerivesDistinctKeys(): void
    {
        $keys = new KeyRing(random_bytes(32));

        self::assertCount(3, array_unique([$keys->submissionKey(), $keys->totpKey(), $keys->rateLimitKey()]));
    }
}
