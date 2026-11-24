<?php

declare(strict_types=1);

namespace SecureContact\Mail;

interface SubmissionNotifier
{
    public function notify(string $submissionId): bool;
}
