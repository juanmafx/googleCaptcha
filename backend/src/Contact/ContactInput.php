<?php

declare(strict_types=1);

namespace SecureContact\Contact;

final readonly class ContactInput
{
    public function __construct(
        public string $name,
        public string $email,
        public string $message,
    ) {
    }
}
