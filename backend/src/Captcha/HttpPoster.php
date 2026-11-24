<?php

declare(strict_types=1);

namespace SecureContact\Captcha;

interface HttpPoster
{
    public function post(string $url, array $fields): ?string;
}
