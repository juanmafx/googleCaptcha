<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SecureContact\Config\AppConfig;
use SecureContact\Config\ConfigurationException;

final class AppConfigTest extends TestCase
{
    private static function valid(): array
    {
        return [
            'APP_MASTER_KEY' => base64_encode(str_repeat('k', 32)),
            'RECAPTCHA_SITE_KEY' => 'site',
            'RECAPTCHA_SECRET_KEY' => 'secret',
            'DATABASE_DSN' => 'mysql:host=db;dbname=contact',
            'DATABASE_USER' => 'contact',
            'MAILER_DSN' => 'null://null',
            'MAIL_FROM' => 'from@example.com',
            'MAIL_TO' => 'to@example.com',
        ];
    }

    public function testDefaultsToProduction(): void
    {
        $config = AppConfig::fromEnvironment(self::valid());

        self::assertTrue($config->production);
        self::assertNull($config->recaptchaHostname);
    }

    public static function invalidOverrides(): array
    {
        return [
            'missing key' => [['APP_MASTER_KEY' => '']],
            'short key' => [['APP_MASTER_KEY' => base64_encode('short')]],
            'non base64 key' => [['APP_MASTER_KEY' => '***']],
            'missing secret' => [['RECAPTCHA_SECRET_KEY' => ' ']],
            'unknown environment' => [['APP_ENV' => 'staging']],
            'header injection in sender' => [['MAIL_FROM' => "a@example.com\r\nBcc: b@example.com"]],
            'invalid recipient' => [['MAIL_TO' => 'nope']],
        ];
    }

    #[DataProvider('invalidOverrides')]
    public function testRejectsInvalidConfiguration(array $overrides): void
    {
        $this->expectException(ConfigurationException::class);
        AppConfig::fromEnvironment([...self::valid(), ...$overrides]);
    }
}
