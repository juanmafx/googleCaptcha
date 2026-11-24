<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Captcha\CaptchaVerifier;

final class StubCaptcha implements CaptchaVerifier
{
    public int $calls = 0;

    public function __construct(public bool $result = true)
    {
    }

    public function verify(string $token): bool
    {
        ++$this->calls;

        return $this->result;
    }
}
