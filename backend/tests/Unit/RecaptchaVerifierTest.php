<?php

declare(strict_types=1);

namespace SecureContact\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SecureContact\Captcha\RecaptchaVerifier;
use SecureContact\Tests\Support\StubHttpPoster;

final class RecaptchaVerifierTest extends TestCase
{
    public function testSendsSecretInBodyOverHttps(): void
    {
        $http = new StubHttpPoster('{"success":true}');

        self::assertTrue((new RecaptchaVerifier($http, 'server-secret'))->verify('token&secret=evil'));
        self::assertSame('https://www.google.com/recaptcha/api/siteverify', $http->requests[0]['url']);
        self::assertSame('server-secret', $http->requests[0]['fields']['secret']);
        self::assertSame('token&secret=evil', $http->requests[0]['fields']['response']);
    }

    public static function failingBodies(): array
    {
        return [
            'network failure' => [null],
            'invalid json' => ['not json'],
            'scalar json' => ['true'],
            'unsuccessful' => ['{"success":false}'],
            'truthy string' => ['{"success":"true"}'],
            'missing field' => ['{}'],
        ];
    }

    #[DataProvider('failingBodies')]
    public function testFailsClosed(?string $body): void
    {
        self::assertFalse((new RecaptchaVerifier(new StubHttpPoster($body), 'secret'))->verify('token'));
    }

    public function testSkipsNetworkForUnusableTokens(): void
    {
        $http = new StubHttpPoster('{"success":true}');
        $verifier = new RecaptchaVerifier($http, 'secret');

        self::assertFalse($verifier->verify(''));
        self::assertFalse($verifier->verify(str_repeat('a', 4097)));
        self::assertSame([], $http->requests);
    }

    public function testEnforcesExpectedHostname(): void
    {
        $matching = new StubHttpPoster('{"success":true,"hostname":"contact.example"}');
        $other = new StubHttpPoster('{"success":true,"hostname":"evil.example"}');

        self::assertTrue((new RecaptchaVerifier($matching, 'secret', 'contact.example'))->verify('token'));
        self::assertFalse((new RecaptchaVerifier($other, 'secret', 'contact.example'))->verify('token'));
    }
}
