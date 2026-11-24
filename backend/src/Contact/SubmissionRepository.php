<?php

declare(strict_types=1);

namespace SecureContact\Contact;

interface SubmissionRepository
{
    public function save(EncryptedSubmission $submission): void;

    public function recent(int $limit, int $offset): array;

    public function count(): int;
}
