<?php

declare(strict_types=1);

namespace SecureContact\Cli;

use InvalidArgumentException;
use SecureContact\Admin\AdminAuthenticator;
use SecureContact\Admin\AdminRepository;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;

final readonly class CreateAdminCommand
{
    private const int MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private AdminRepository $admins,
        private PasswordHasher $passwords,
        private TotpService $totp,
        private FieldCipher $totpCipher,
    ) {
    }

    public function prepareSecret(): string
    {
        return $this->totp->generateSecret();
    }

    public function provisioningUri(string $username, string $secret): string
    {
        return $this->totp->provisioningUri($secret, $username, 'Secure Contact Admin');
    }

    public function create(string $username, string $password, string $secret, string $confirmationCode): int
    {
        if (preg_match('/\A[a-z0-9._-]{3,64}\z/', $username) !== 1) {
            throw new InvalidArgumentException('Username must be 3-64 characters of a-z, 0-9, dot, underscore, or hyphen.');
        }
        if (strlen($password) < self::MIN_PASSWORD_LENGTH || strlen($password) > PasswordHasher::MAX_PASSWORD_BYTES) {
            throw new InvalidArgumentException('Password must be between 12 and 1024 bytes.');
        }
        if ($this->admins->findByUsername($username) !== null) {
            throw new InvalidArgumentException('That username already exists.');
        }
        $confirmedStep = $this->totp->matchStep($secret, $confirmationCode);
        if ($confirmedStep === null) {
            throw new InvalidArgumentException('The authenticator code did not match.');
        }

        $id = $this->admins->create(
            $username,
            $this->passwords->hash($password),
            $this->totpCipher->encrypt($secret, AdminAuthenticator::totpContext($username)),
        );
        $this->admins->advanceTotpStep($id, $confirmedStep);

        return $id;
    }
}
