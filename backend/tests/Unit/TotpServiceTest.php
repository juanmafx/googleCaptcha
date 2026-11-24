<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;
use PHPUnit\Framework\TestCase;
use SecureContact\Security\TotpService;
use SecureContact\Tests\Support\FixedClock;

final class TotpServiceTest extends TestCase
{
    public function testLibraryMatchesRfc6238Vector(): void
    {
        $totp = TOTP::createFromSecret(Base32::encodeUpperUnpadded('12345678901234567890'));
        $totp->setDigits(8);

        self::assertSame('94287082', $totp->at(59));
    }

    public function testAcceptsCurrentAndAdjacentSteps(): void
    {
        $clock = new FixedClock(1800000000);
        $service = new TotpService($clock);
        $secret = $service->generateSecret();
        $totp = TOTP::createFromSecret($secret);
        $step = intdiv($clock->time, 30);

        self::assertSame($step, $service->matchStep($secret, $totp->at($clock->time)));
        self::assertSame($step - 1, $service->matchStep($secret, $totp->at($clock->time - 30)));
        self::assertSame($step + 1, $service->matchStep($secret, $totp->at($clock->time + 30)));
    }

    public function testRejectsCodeOutsideDriftWindow(): void
    {
        $clock = new FixedClock(1800000000);
        $service = new TotpService($clock);
        $secret = $service->generateSecret();
        $stale = TOTP::createFromSecret($secret)->at($clock->time - 300);
        $window = array_map(
            static fn (int $offset): string => TOTP::createFromSecret($secret)->at($clock->time + $offset),
            [-30, 0, 30],
        );

        if (in_array($stale, $window, true)) {
            $stale = $stale === '000000' ? '000001' : '000000';
        }

        self::assertNull($service->matchStep($secret, $stale));
    }

    public function testRejectsMalformedCodes(): void
    {
        $service = new TotpService(new FixedClock());
        $secret = $service->generateSecret();

        foreach (['', '12345', '1234567', 'abcdef', "123456\n"] as $code) {
            self::assertNull($service->matchStep($secret, $code));
        }
    }

    public function testProvisioningUriCarriesIssuerAndAccount(): void
    {
        $service = new TotpService(new FixedClock());
        $uri = $service->provisioningUri($service->generateSecret(), 'site-admin', 'Secure Contact Admin');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('site-admin', $uri);
        self::assertStringContainsString('issuer=Secure', $uri);
    }
}
