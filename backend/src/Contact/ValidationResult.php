<?php

declare(strict_types=1);

namespace SecureContact\Contact;

final readonly class ValidationResult
{
    public function __construct(
        public ?ContactInput $input,
        public array $errors,
    ) {
    }
}
