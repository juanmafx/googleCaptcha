<?php

declare(strict_types=1);

namespace SecureContact\Contact;

final readonly class Submission
{
    public function __construct(
        public string $id,
        public string $createdAt,
        public bool $readable,
        public string $name = '',
        public string $email = '',
        public string $message = '',
    ) {
    }
}
