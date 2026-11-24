<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SecureContact\Security\CsrfGuard;
use SecureContact\Tests\Support\ArraySession;

final class CsrfGuardTest extends TestCase
{
    public function testTokenIsStableWithinSession(): void
    {
        $guard = new CsrfGuard(new ArraySession());

        self::assertSame($guard->token(), $guard->token());
        self::assertSame(64, strlen($guard->token()));
    }

    public function testValidatesOnlyMatchingToken(): void
    {
        $guard = new CsrfGuard(new ArraySession());
        $token = $guard->token();

        self::assertTrue($guard->isValid($token));
        self::assertFalse($guard->isValid('forged'));
        self::assertFalse($guard->isValid(''));
    }

    public function testRejectsEverythingWithoutSessionToken(): void
    {
        self::assertFalse((new CsrfGuard(new ArraySession()))->isValid(''));
    }

    public function testRotationInvalidatesPreviousToken(): void
    {
        $guard = new CsrfGuard(new ArraySession());
        $token = $guard->token();
        $guard->rotate();

        self::assertFalse($guard->isValid($token));
        self::assertNotSame($token, $guard->token());
    }
}
