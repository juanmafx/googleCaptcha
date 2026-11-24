<?php

declare(strict_types=1);

namespace SecureContact\Security;

use InvalidArgumentException;
use OTPHP\TOTP;
use SecureContact\Support\Clock;

final readonly class TotpService
{
    private const int PERIOD_SECONDS = 30;
    private const int ALLOWED_DRIFT_STEPS = 1;
    private const int SECRET_BYTES = 20;

    public function __construct(private Clock $clock)
    {
    }

    public function generateSecret(): string
    {
        return TOTP::generate(null, self::SECRET_BYTES)->getSecret();
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        if ($secret === '' || $account === '' || $issuer === '') {
            throw new InvalidArgumentException('Secret, account, and issuer are required.');
        }

        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($account);
        $totp->setIssuer($issuer);

        return $totp->getProvisioningUri();
    }

    public function matchStep(string $secret, string $code): ?int
    {
        if ($secret === '' || preg_match('/\A\d{6}\z/', $code) !== 1) {
            return null;
        }

        $totp = TOTP::createFromSecret($secret);
        $currentStep = intdiv($this->clock->now(), self::PERIOD_SECONDS);
        $matchedStep = null;
        for ($offset = -self::ALLOWED_DRIFT_STEPS; $offset <= self::ALLOWED_DRIFT_STEPS; ++$offset) {
            $step = $currentStep + $offset;
            if ($step >= 0 && hash_equals($totp->at($step * self::PERIOD_SECONDS), $code)) {
                $matchedStep = $step;
            }
        }

        return $matchedStep;
    }
}
