<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Http\Session;

final class ArraySession implements Session
{
    public array $values = [];
    public int $regenerations = 0;
    public int $destructions = 0;

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->values[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->values[$key]);
    }

    public function regenerate(): void
    {
        ++$this->regenerations;
    }

    public function destroy(): void
    {
        ++$this->destructions;
        $this->values = [];
    }
}
