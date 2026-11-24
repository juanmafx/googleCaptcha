<?php

declare(strict_types=1);

namespace SecureContact\Persistence;

use PDO;
use SecureContact\Contact\EncryptedSubmission;
use SecureContact\Contact\SubmissionRepository;

final readonly class PdoSubmissionRepository implements SubmissionRepository
{
    public function __construct(private ConnectionProvider $connections)
    {
    }

    public function save(EncryptedSubmission $submission): void
    {
        $statement = $this->connections->connection()->prepare(
            'INSERT INTO contact_submissions (id, name_encrypted, email_encrypted, message_encrypted, created_at)'
            . ' VALUES (:id, :name, :email, :message, :created_at)',
        );
        $statement->bindValue(':id', $submission->id);
        $statement->bindValue(':name', $submission->encryptedName, PDO::PARAM_LOB);
        $statement->bindValue(':email', $submission->encryptedEmail, PDO::PARAM_LOB);
        $statement->bindValue(':message', $submission->encryptedMessage, PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $submission->createdAt);
        $statement->execute();
    }

    public function recent(int $limit, int $offset): array
    {
        $statement = $this->connections->connection()->prepare(
            'SELECT id, name_encrypted, email_encrypted, message_encrypted, created_at FROM contact_submissions'
            . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        $records = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $records[] = new EncryptedSubmission(
                (string) $row['id'],
                (string) $row['name_encrypted'],
                (string) $row['email_encrypted'],
                (string) $row['message_encrypted'],
                (string) $row['created_at'],
            );
        }

        return $records;
    }

    public function count(): int
    {
        $statement = $this->connections->connection()->query('SELECT COUNT(*) FROM contact_submissions');

        return $statement === false ? 0 : (int) $statement->fetchColumn();
    }
}
