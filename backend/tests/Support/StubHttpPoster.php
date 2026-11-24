<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Captcha\HttpPoster;

final class StubHttpPoster implements HttpPoster
{
    public array $requests = [];

    public function __construct(private readonly ?string $body)
    {
    }

    public function post(string $url, array $fields): ?string
    {
        $this->requests[] = ['url' => $url, 'fields' => $fields];

        return $this->body;
    }
}
