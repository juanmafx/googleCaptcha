<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Mail\SubmissionNotifier;

final class RecordingNotifier implements SubmissionNotifier
{
    public array $ids = [];

    public function notify(string $submissionId): bool
    {
        $this->ids[] = $submissionId;

        return true;
    }
}
