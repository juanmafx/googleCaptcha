<?php

declare(strict_types=1);

namespace SecureContact\Contact;

use SecureContact\Mail\SubmissionNotifier;
use SecureContact\Security\DecryptionFailed;
use SecureContact\Security\FieldCipher;
use SecureContact\Support\Clock;

final readonly class ContactService
{
    public const int PAGE_SIZE = 25;

    public function __construct(
        private SubmissionRepository $submissions,
        private FieldCipher $cipher,
        private SubmissionNotifier $notifier,
        private Clock $clock,
    ) {
    }

    public function submit(ContactInput $input): string
    {
        $id = self::uuid();
        $this->submissions->save(new EncryptedSubmission(
            $id,
            $this->cipher->encrypt($input->name, self::context($id, 'name')),
            $this->cipher->encrypt($input->email, self::context($id, 'email')),
            $this->cipher->encrypt($input->message, self::context($id, 'message')),
            gmdate('Y-m-d H:i:s', $this->clock->now()),
        ));
        $this->notifier->notify($id);

        return $id;
    }

    public function page(int $page): array
    {
        $records = $this->submissions->recent(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        return array_map($this->decrypt(...), $records);
    }

    public function pageCount(): int
    {
        return max(1, (int) ceil($this->submissions->count() / self::PAGE_SIZE));
    }

    private function decrypt(EncryptedSubmission $record): Submission
    {
        try {
            return new Submission(
                $record->id,
                $record->createdAt,
                true,
                $this->cipher->decrypt($record->encryptedName, self::context($record->id, 'name')),
                $this->cipher->decrypt($record->encryptedEmail, self::context($record->id, 'email')),
                $this->cipher->decrypt($record->encryptedMessage, self::context($record->id, 'message')),
            );
        } catch (DecryptionFailed) {
            return new Submission($record->id, $record->createdAt, false);
        }
    }

    private static function context(string $id, string $field): string
    {
        return 'submission:' . $id . ':' . $field;
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
