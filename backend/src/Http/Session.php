<?php

declare(strict_types=1);

namespace SecureContact\Http;

interface Session
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    public function regenerate(): void;

    public function destroy(): void;
}
