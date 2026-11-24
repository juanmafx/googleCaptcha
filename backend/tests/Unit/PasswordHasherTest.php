<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SecureContact\Security\PasswordHasher;

final class PasswordHasherTest extends TestCase
{
    private const array FAST = ['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1];

    public function testProducesSaltedArgon2idHashes(): void
    {
        $hasher = new PasswordHasher(self::FAST);
        $first = $hasher->hash('a long passphrase');
        $second = $hasher->hash('a long passphrase');

        self::assertStringStartsWith('$argon2id$', $first);
        self::assertNotSame($first, $second);
        self::assertTrue($hasher->verify('a long passphrase', $first));
        self::assertFalse($hasher->verify('wrong passphrase', $first));
    }

    public function testDefaultCostMeetsDocumentedParameters(): void
    {
        $info = password_get_info((new PasswordHasher())->hash('a long passphrase'));

        self::assertSame('argon2id', $info['algoName']);
        self::assertSame(65536, $info['options']['memory_cost']);
        self::assertSame(3, $info['options']['time_cost']);
    }

    public function testFlagsWeakerHashForRehash(): void
    {
        $weak = (new PasswordHasher(self::FAST))->hash('a long passphrase');

        self::assertTrue((new PasswordHasher())->needsRehash($weak));
    }

    public function testRejectsOversizedPassword(): void
    {
        $hasher = new PasswordHasher(self::FAST);
        $password = str_repeat('a', PasswordHasher::MAX_PASSWORD_BYTES + 1);

        self::assertFalse($hasher->verify($password, $hasher->hash($password)));
    }
}
