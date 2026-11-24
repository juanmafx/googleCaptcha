<?php

declare(strict_types=1);

namespace SecureContact\Http;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public string $remoteAddress = '',
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $method = is_string($_SERVER['REQUEST_METHOD'] ?? null) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $remoteAddress = is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';

        return new self($method, is_string($path) ? $path : '/', $_GET, $_POST, $remoteAddress);
    }

    public function postString(string $key): string
    {
        $value = $this->post[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    public function rateLimitAddress(): string
    {
        if (filter_var($this->remoteAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $this->remoteAddress;
        }

        $packed = (string) inet_pton($this->remoteAddress);
        if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return bin2hex(substr($packed, 0, 8)) . '::/64';
    }

    public function queryPage(): int
    {
        $page = filter_var($this->query['page'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000000],
        ]);

        return is_int($page) ? $page : 1;
    }
}
