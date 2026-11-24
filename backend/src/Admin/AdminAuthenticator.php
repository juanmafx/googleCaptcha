<?php

declare(strict_types=1);

namespace SecureContact\Admin;

use SecureContact\Security\DecryptionFailed;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;

final readonly class AdminAuthenticator
{
    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $passwords,
        private TotpService $totp,
        private FieldCipher $totpCipher,
    ) {
    }

    public static function totpContext(string $username): string
    {
        return 'admin-totp:' . $username;
    }

    public function verifyPassword(string $username, string $password): ?AdminUser
    {
        $admin = preg_match('/\A[a-z0-9._-]{3,64}\z/', $username) === 1
            ? $this->admins->findByUsername($username)
            : null;
        if ($admin === null) {
            $this->passwords->burnVerification($password);

            return null;
        }
        if (!$this->passwords->verify($password, $admin->passwordHash)) {
            return null;
        }
        if ($this->passwords->needsRehash($admin->passwordHash)) {
            $this->admins->updatePasswordHash($admin->id, $this->passwords->hash($password));
        }

        return $admin;
    }

    public function verifySecondFactor(int $adminId, string $code): bool
    {
        $admin = $this->admins->findById($adminId);
        if ($admin === null) {
            return false;
        }

        try {
            $secret = $this->totpCipher->decrypt($admin->encryptedTotpSecret, self::totpContext($admin->username));
        } catch (DecryptionFailed) {
            return false;
        }

        $step = $this->totp->matchStep($secret, $code);

        return $step !== null && $this->admins->advanceTotpStep($admin->id, $step);
    }
}
