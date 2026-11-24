<?php

declare(strict_types=1);

namespace SecureContact\Config;

final readonly class AppConfig
{
    private const int MASTER_KEY_BYTES = SODIUM_CRYPTO_KDF_KEYBYTES;

    public function __construct(
        public bool $production,
        public string $masterKey,
        public string $recaptchaSiteKey,
        public string $recaptchaSecret,
        public ?string $recaptchaHostname,
        public string $databaseDsn,
        public string $databaseUser,
        public string $databasePassword,
        public string $mailerDsn,
        public string $mailFrom,
        public string $mailTo,
    ) {
    }

    public static function fromEnvironment(array $environment): self
    {
        $appEnvironment = $environment['APP_ENV'] ?? 'production';
        if (!in_array($appEnvironment, ['production', 'development'], true)) {
            throw new ConfigurationException('APP_ENV must be production or development.');
        }

        $masterKey = base64_decode(self::required($environment, 'APP_MASTER_KEY'), true);
        if ($masterKey === false || strlen($masterKey) !== self::MASTER_KEY_BYTES) {
            throw new ConfigurationException('APP_MASTER_KEY must be base64 for exactly 32 bytes.');
        }

        $hostname = trim($environment['RECAPTCHA_EXPECTED_HOSTNAME'] ?? '');

        return new self(
            $appEnvironment === 'production',
            $masterKey,
            self::required($environment, 'RECAPTCHA_SITE_KEY'),
            self::required($environment, 'RECAPTCHA_SECRET_KEY'),
            $hostname === '' ? null : $hostname,
            self::required($environment, 'DATABASE_DSN'),
            self::required($environment, 'DATABASE_USER'),
            $environment['DATABASE_PASSWORD'] ?? '',
            self::required($environment, 'MAILER_DSN'),
            self::address($environment, 'MAIL_FROM'),
            self::address($environment, 'MAIL_TO'),
        );
    }

    private static function required(array $environment, string $name): string
    {
        $value = trim($environment[$name] ?? '');
        if ($value === '') {
            throw new ConfigurationException($name . ' is required.');
        }

        return $value;
    }

    private static function address(array $environment, string $name): string
    {
        $value = self::required($environment, $name);
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ConfigurationException($name . ' must be a valid email address.');
        }

        return $value;
    }
}
