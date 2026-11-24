<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Contact\EncryptedSubmission;
use SecureContact\Contact\SubmissionRepository;

final class InMemorySubmissions implements SubmissionRepository
{
    public array $records = [];

    public function save(EncryptedSubmission $submission): void
    {
        $this->records[] = $submission;
    }

    public function recent(int $limit, int $offset): array
    {
        return array_slice(array_reverse($this->records), $offset, $limit);
    }

    public function count(): int
    {
        return count($this->records);
    }
}
