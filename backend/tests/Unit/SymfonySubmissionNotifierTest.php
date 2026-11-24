<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SecureContact\Mail\SymfonySubmissionNotifier;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class SymfonySubmissionNotifierTest extends TestCase
{
    public function testSendsNotificationWithoutPersonalData(): void
    {
        $transport = new class () implements TransportInterface {
            public ?RawMessage $message = null;

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->message = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'recording://';
            }
        };

        $sent = (new SymfonySubmissionNotifier($transport, 'from@example.com', 'to@example.com'))->notify('record-id');

        self::assertTrue($sent);
        self::assertInstanceOf(Email::class, $transport->message);
        self::assertSame('New contact form submission', $transport->message->getSubject());
        self::assertStringContainsString('record-id', (string) $transport->message->getTextBody());
        self::assertSame([], $transport->message->getReplyTo());
    }

    public function testReportsTransportFailure(): void
    {
        $transport = new class () implements TransportInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                throw new TransportException('unreachable');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        };
        $previous = ini_set('error_log', '/dev/null');

        try {
            self::assertFalse((new SymfonySubmissionNotifier($transport, 'from@example.com', 'to@example.com'))->notify('record-id'));
        } finally {
            ini_set('error_log', (string) $previous);
        }
    }
}
