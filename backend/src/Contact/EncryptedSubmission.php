<?php

declare(strict_types=1);

namespace SecureContact\Contact;

final readonly class EncryptedSubmission
{
    public function __construct(
        public string $id,
        public string $encryptedName,
        public string $encryptedEmail,
        public string $encryptedMessage,
        public string $createdAt,
    ) {
    }
}
