<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use InvalidArgumentException;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use SecureContact\Admin\AdminAuthenticator;
use SecureContact\Cli\CreateAdminCommand;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;
use SecureContact\Tests\Support\FixedClock;
use SecureContact\Tests\Support\InMemoryAdmins;

final class CreateAdminCommandTest extends TestCase
{
    private const string PASSWORD = 'a sufficiently long passphrase';

    private InMemoryAdmins $admins;
    private FixedClock $clock;
    private FieldCipher $cipher;
    private CreateAdminCommand $command;

    protected function setUp(): void
    {
        $this->admins = new InMemoryAdmins();
        $this->clock = new FixedClock();
        $this->cipher = new FieldCipher(random_bytes(32));
        $this->command = new CreateAdminCommand(
            $this->admins,
            new PasswordHasher(['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]),
            new TotpService($this->clock),
            $this->cipher,
        );
    }

    public function testCreatesAdminWithEncryptedSecret(): void
    {
        $secret = $this->command->prepareSecret();
        $id = $this->command->create('site-admin', self::PASSWORD, $secret, TOTP::createFromSecret($secret)->at($this->clock->time));
        $admin = $this->admins->users[$id];

        self::assertStringStartsWith('$argon2id$', $admin->passwordHash);
        self::assertStringNotContainsString($secret, $admin->encryptedTotpSecret);
        self::assertSame($secret, $this->cipher->decrypt($admin->encryptedTotpSecret, AdminAuthenticator::totpContext('site-admin')));
    }

    public function testRejectsWrongConfirmationCode(): void
    {
        $secret = $this->command->prepareSecret();
        $valid = TOTP::createFromSecret($secret);
        $candidates = array_map(static fn (int $offset): string => $valid->at(1800000000 + $offset), [-30, 0, 30]);
        $wrong = in_array('000000', $candidates, true) ? '111111' : '000000';

        $this->expectException(InvalidArgumentException::class);
        $this->command->create('site-admin', self::PASSWORD, $secret, $wrong);
    }

    public function testRejectsShortPassword(): void
    {
        $secret = $this->command->prepareSecret();

        $this->expectException(InvalidArgumentException::class);
        $this->command->create('site-admin', 'short', $secret, TOTP::createFromSecret($secret)->at($this->clock->time));
    }

    public function testRejectsInvalidUsername(): void
    {
        $secret = $this->command->prepareSecret();

        $this->expectException(InvalidArgumentException::class);
        $this->command->create('Bad User', self::PASSWORD, $secret, TOTP::createFromSecret($secret)->at($this->clock->time));
    }

    public function testRejectsDuplicateUsername(): void
    {
        $secret = $this->command->prepareSecret();
        $code = TOTP::createFromSecret($secret)->at($this->clock->time);
        $this->command->create('site-admin', self::PASSWORD, $secret, $code);

        $this->expectException(InvalidArgumentException::class);
        $this->command->create('site-admin', self::PASSWORD, $secret, $code);
    }
}
