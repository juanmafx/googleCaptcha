<?php

declare(strict_types=1);

namespace SecureContact\Persistence;

use PDO;
use SecureContact\Admin\AdminRepository;
use SecureContact\Admin\AdminUser;
use SecureContact\Support\Clock;

final readonly class PdoAdminRepository implements AdminRepository
{
    private const string COLUMNS = 'id, username, password_hash, totp_secret_encrypted';

    public function __construct(private ConnectionProvider $connections, private Clock $clock)
    {
    }

    public function findByUsername(string $username): ?AdminUser
    {
        $statement = $this->connections->connection()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM admin_users WHERE username = :username',
        );
        $statement->execute([':username' => $username]);

        return self::hydrate($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function findById(int $id): ?AdminUser
    {
        $statement = $this->connections->connection()->prepare(
            'SELECT ' . self::COLUMNS . ' FROM admin_users WHERE id = :id',
        );
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return self::hydrate($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function create(string $username, string $passwordHash, string $encryptedTotpSecret): int
    {
        $connection = $this->connections->connection();
        $now = gmdate('Y-m-d H:i:s', $this->clock->now());
        $statement = $connection->prepare(
            'INSERT INTO admin_users (username, password_hash, totp_secret_encrypted, created_at, updated_at)'
            . ' VALUES (:username, :password_hash, :totp, :created_at, :updated_at)',
        );
        $statement->bindValue(':username', $username);
        $statement->bindValue(':password_hash', $passwordHash);
        $statement->bindValue(':totp', $encryptedTotpSecret, PDO::PARAM_LOB);
        $statement->bindValue(':created_at', $now);
        $statement->bindValue(':updated_at', $now);
        $statement->execute();

        return (int) $connection->lastInsertId();
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $statement = $this->connections->connection()->prepare(
            'UPDATE admin_users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id',
        );
        $statement->bindValue(':password_hash', $passwordHash);
        $statement->bindValue(':updated_at', gmdate('Y-m-d H:i:s', $this->clock->now()));
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function advanceTotpStep(int $id, int $step): bool
    {
        $statement = $this->connections->connection()->prepare(
            'UPDATE admin_users SET last_totp_step = :step, updated_at = :updated_at'
            . ' WHERE id = :id AND (last_totp_step IS NULL OR last_totp_step < :minimum_step)',
        );
        $statement->bindValue(':step', $step, PDO::PARAM_INT);
        $statement->bindValue(':updated_at', gmdate('Y-m-d H:i:s', $this->clock->now()));
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':minimum_step', $step, PDO::PARAM_INT);
        $statement->execute();

        return $statement->rowCount() === 1;
    }

    private static function hydrate(mixed $row): ?AdminUser
    {
        if (!is_array($row)) {
            return null;
        }

        return new AdminUser(
            (int) $row['id'],
            (string) $row['username'],
            (string) $row['password_hash'],
            (string) $row['totp_secret_encrypted'],
        );
    }
}
