<?php

declare(strict_types=1);

namespace SecureContact\Tests\Functional;

use PHPUnit\Framework\TestCase;
use SecureContact\Tests\Support\Harness;

final class ContactFlowTest extends TestCase
{
    private const array VALID = [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => 'Hello there',
        'g-recaptcha-response' => 'token',
    ];

    public function testFormRendersCsrfTokenCaptchaAndHeaders(): void
    {
        $harness = new Harness();
        $response = $harness->get('/');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('name="csrf_token"', $response->body);
        self::assertStringContainsString('data-sitekey="public-site-key"', $response->body);
        self::assertStringContainsString("default-src 'none'", $response->headers['Content-Security-Policy']);
        self::assertStringNotContainsString('unsafe-inline', $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertArrayHasKey('Strict-Transport-Security', $response->headers);
    }

    public function testValidSubmissionIsEncryptedAndNotified(): void
    {
        $harness = new Harness();
        $response = $harness->post('/contact', self::VALID);

        self::assertSame(303, $response->status);
        self::assertSame('/thanks', $response->headers['Location']);
        self::assertCount(1, $harness->submissions->records);
        $record = $harness->submissions->records[0];
        self::assertStringNotContainsString('Ada', $record->encryptedName);
        self::assertStringNotContainsString('ada@example.com', $record->encryptedEmail);
        self::assertStringNotContainsString('Hello', $record->encryptedMessage);
        self::assertSame([$record->id], $harness->notifier->ids);
    }

    public function testMissingCsrfIsRejectedBeforeAnyWork(): void
    {
        $harness = new Harness();
        $response = $harness->post('/contact', self::VALID, false);

        self::assertSame(403, $response->status);
        self::assertSame(0, $harness->captcha->calls);
        self::assertSame([], $harness->submissions->records);
    }

    public function testFailedCaptchaStoresNothing(): void
    {
        $harness = new Harness();
        $harness->captcha->result = false;
        $response = $harness->post('/contact', self::VALID);

        self::assertSame(422, $response->status);
        self::assertStringContainsString('Robot verification failed', $response->body);
        self::assertSame([], $harness->submissions->records);
        self::assertSame([], $harness->notifier->ids);
    }

    public function testHeaderInjectionIsRejectedWithoutMail(): void
    {
        $harness = new Harness();
        $response = $harness->post('/contact', [...self::VALID, 'email' => "ada@example.com\r\nBcc: victim@example.com"]);

        self::assertSame(422, $response->status);
        self::assertSame(0, $harness->captcha->calls);
        self::assertSame([], $harness->notifier->ids);
    }

    public function testReflectedValuesAreEscaped(): void
    {
        $harness = new Harness();
        $response = $harness->post('/contact', [
            ...self::VALID,
            'name' => '"><script>alert(1)</script>',
            'email' => 'bad',
        ]);

        self::assertStringNotContainsString('<script>alert(1)', $response->body);
        self::assertStringContainsString('&lt;script&gt;', $response->body);
    }

    public function testCaptchaTokenIsNeverReflected(): void
    {
        $harness = new Harness();
        $harness->captcha->result = false;
        $response = $harness->post('/contact', [...self::VALID, 'g-recaptcha-response' => '<img src=x onerror=alert(1)>']);

        self::assertStringNotContainsString('onerror', $response->body);
    }

    public function testArrayParametersDoNotCrash(): void
    {
        $harness = new Harness();
        $response = $harness->post('/contact', [...self::VALID, 'name' => ['x'], 'email' => ['y']]);

        self::assertSame(422, $response->status);
    }

    public function testRateLimitBlocksSixthSubmission(): void
    {
        $harness = new Harness();
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            self::assertSame(303, $harness->post('/contact', self::VALID)->status);
        }
        $blocked = $harness->post('/contact', self::VALID);

        self::assertSame(429, $blocked->status);
        self::assertSame('900', $blocked->headers['Retry-After']);
        self::assertCount(5, $harness->submissions->records);
    }

    public function testNonCaptchaPagesDropGoogleFromCsp(): void
    {
        $harness = new Harness();

        self::assertStringNotContainsString('google', $harness->get('/admin/login')->headers['Content-Security-Policy']);
    }

    public function testUnknownRouteAndStylesheet(): void
    {
        $harness = new Harness();

        self::assertSame(404, $harness->get('/result.php')->status);
        $css = $harness->get('/assets/app.css');
        self::assertSame(200, $css->status);
        self::assertStringStartsWith('text/css', $css->headers['Content-Type']);
        self::assertStringContainsString('max-age', $css->headers['Cache-Control']);
    }
}
