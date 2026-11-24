<?php

declare(strict_types=1);

namespace SecureContact\Mail;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final readonly class SymfonySubmissionNotifier implements SubmissionNotifier
{
    public function __construct(
        private TransportInterface $transport,
        private string $from,
        private string $to,
    ) {
    }

    public function notify(string $submissionId): bool
    {
        $email = (new Email())
            ->from(new Address($this->from))
            ->to(new Address($this->to))
            ->subject('New contact form submission')
            ->text('A new submission is waiting in the admin area. Reference: ' . $submissionId);

        try {
            $this->transport->send($email);

            return true;
        } catch (TransportExceptionInterface) {
            error_log('Contact notification could not be delivered.');

            return false;
        }
    }
}
