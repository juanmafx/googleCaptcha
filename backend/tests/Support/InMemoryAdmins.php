<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use SecureContact\Admin\AdminRepository;
use SecureContact\Admin\AdminUser;

final class InMemoryAdmins implements AdminRepository
{
    public array $users = [];
    public array $steps = [];

    public function findByUsername(string $username): ?AdminUser
    {
        foreach ($this->users as $user) {
            if ($user->username === $username) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?AdminUser
    {
        return $this->users[$id] ?? null;
    }

    public function create(string $username, string $passwordHash, string $encryptedTotpSecret): int
    {
        $id = count($this->users) + 1;
        $this->users[$id] = new AdminUser($id, $username, $passwordHash, $encryptedTotpSecret);

        return $id;
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $user = $this->users[$id];
        $this->users[$id] = new AdminUser($id, $user->username, $passwordHash, $user->encryptedTotpSecret);
    }

    public function advanceTotpStep(int $id, int $step): bool
    {
        if (($this->steps[$id] ?? -1) >= $step) {
            return false;
        }
        $this->steps[$id] = $step;

        return true;
    }
}
