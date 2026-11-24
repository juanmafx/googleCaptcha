<?php

declare(strict_types=1);

namespace SecureContact\Captcha;

interface CaptchaVerifier
{
    public function verify(string $token): bool;
}
