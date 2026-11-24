<?php

declare(strict_types=1);

namespace SecureContact\Support;

interface Clock
{
    public function now(): int;
}
