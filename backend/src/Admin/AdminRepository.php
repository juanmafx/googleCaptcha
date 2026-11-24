<?php

declare(strict_types=1);

namespace SecureContact\Admin;

interface AdminRepository
{
    public function findByUsername(string $username): ?AdminUser;

    public function findById(int $id): ?AdminUser;

    public function create(string $username, string $passwordHash, string $encryptedTotpSecret): int;

    public function updatePasswordHash(int $id, string $passwordHash): void;

    public function advanceTotpStep(int $id, int $step): bool;
}
