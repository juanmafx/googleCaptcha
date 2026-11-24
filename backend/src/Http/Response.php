<?php

declare(strict_types=1);

namespace SecureContact\Http;

final readonly class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirect(string $path): self
    {
        return new self('', 303, ['Location' => $path]);
    }

    public function withHeaders(array $headers): self
    {
        return new self($this->body, $this->status, [...$headers, ...$this->headers]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
