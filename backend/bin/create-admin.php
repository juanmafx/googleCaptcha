<?php

declare(strict_types=1);

use SecureContact\Cli\CreateAdminCommand;
use SecureContact\Config\AppConfig;
use SecureContact\Persistence\ConnectionProvider;
use SecureContact\Persistence\PdoAdminRepository;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\KeyRing;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;
use SecureContact\Support\SystemClock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || !is_string($argv[1] ?? null)) {
    fwrite(STDERR, "Usage: php backend/bin/create-admin.php <username>\n");
    exit(2);
}

$readLine = static function (string $prompt, bool $hidden): string {
    fwrite(STDOUT, $prompt);
    $interactive = stream_isatty(STDIN);
    if ($hidden && $interactive) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($hidden && $interactive) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }

    return $line === false ? '' : rtrim($line, "\r\n");
};

try {
    $config = AppConfig::fromEnvironment(getenv());
    $clock = new SystemClock();
    $command = new CreateAdminCommand(
        new PdoAdminRepository(new ConnectionProvider($config->databaseDsn, $config->databaseUser, $config->databasePassword), $clock),
        new PasswordHasher(),
        new TotpService($clock),
        new FieldCipher((new KeyRing($config->masterKey))->totpKey()),
    );

    $password = $readLine('Password: ', true);
    $secret = $command->prepareSecret();
    fwrite(STDOUT, 'Add this URI to your authenticator app: ' . $command->provisioningUri($argv[1], $secret) . PHP_EOL);
    $code = $readLine('Enter the current 6-digit code: ', false);
    $id = $command->create($argv[1], $password, $secret, $code);
    fwrite(STDOUT, 'Created admin user ' . $id . PHP_EOL);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Admin creation failed: ' . $exception::class . PHP_EOL);
    exit(1);
}
