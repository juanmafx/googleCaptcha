<?php

declare(strict_types=1);

namespace SecureContact\Tests\Functional;

use PHPUnit\Framework\TestCase;
use SecureContact\Contact\EncryptedSubmission;
use SecureContact\Tests\Support\Harness;

final class AdminFlowTest extends TestCase
{
    public function testDashboardRequiresAuthentication(): void
    {
        $response = (new Harness())->get('/admin');

        self::assertSame(303, $response->status);
        self::assertSame('/admin/login', $response->headers['Location']);
    }

    public function testPasswordAloneDoesNotGrantAccess(): void
    {
        $harness = new Harness();
        $response = $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);

        self::assertSame('/admin/two-factor', $response->headers['Location']);
        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
    }

    public function testFullLoginRegeneratesSessionAndShowsEscapedData(): void
    {
        $harness = new Harness();
        $harness->post('/contact', [
            'name' => '<b>Ada</b>',
            'email' => 'ada@example.com',
            'message' => '<script>alert(1)</script>',
            'g-recaptcha-response' => 'token',
        ]);
        $harness->signIn();
        $dashboard = $harness->get('/admin');

        self::assertSame(2, $harness->session->regenerations);
        self::assertSame(200, $dashboard->status);
        self::assertStringContainsString('ada@example.com', $dashboard->body);
        self::assertStringContainsString('&lt;b&gt;Ada', $dashboard->body);
        self::assertStringNotContainsString('<script>alert(1)', $dashboard->body);
    }

    public function testInvalidCredentialsShareOneMessage(): void
    {
        $harness = new Harness();
        $wrongPassword = $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => 'wrong password value']);
        $unknownUser = $harness->post('/admin/login', ['username' => 'nobody', 'password' => 'wrong password value']);

        self::assertSame(401, $wrongPassword->status);
        self::assertSame(401, $unknownUser->status);
        self::assertStringContainsString('Invalid credentials.', $wrongPassword->body);
        self::assertStringContainsString('Invalid credentials.', $unknownUser->body);
    }

    public function testWrongCodeIsRejected(): void
    {
        $harness = new Harness();
        $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);
        $wrong = $harness->currentCode() === '000000' ? '000001' : '000000';

        self::assertSame(401, $harness->post('/admin/two-factor', ['code' => $wrong])->status);
        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
    }

    public function testTotpCodeCannotBeReplayed(): void
    {
        $harness = new Harness();
        $code = $harness->currentCode();
        $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);
        self::assertSame('/admin', $harness->post('/admin/two-factor', ['code' => $code])->headers['Location']);

        $harness->post('/admin/logout', []);
        $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);

        self::assertSame(401, $harness->post('/admin/two-factor', ['code' => $code])->status);
    }

    public function testTotpAttemptsAreRateLimited(): void
    {
        $harness = new Harness();
        $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);
        $wrong = $harness->currentCode() === '000000' ? '000001' : '000000';
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $harness->post('/admin/two-factor', ['code' => $wrong]);
        }

        self::assertSame(429, $harness->post('/admin/two-factor', ['code' => $harness->currentCode()])->status);
    }

    public function testLoginAttemptsAreRateLimitedPerAccount(): void
    {
        $harness = new Harness();
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => 'wrong password value']);
        }

        self::assertSame(429, $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD])->status);
    }

    public function testPendingLoginExpires(): void
    {
        $harness = new Harness();
        $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => Harness::PASSWORD]);
        $harness->clock->time += 301;

        self::assertSame('/admin/login', $harness->get('/admin/two-factor')->headers['Location']);
    }

    public function testIdleSessionExpires(): void
    {
        $harness = new Harness();
        $harness->signIn();
        $harness->clock->time += 901;

        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
    }

    public function testAbsoluteSessionLifetimeIsEnforced(): void
    {
        $harness = new Harness();
        $harness->signIn();
        for ($elapsed = 0; $elapsed < 28800; $elapsed += 800) {
            $harness->clock->time += 800;
            $response = $harness->get('/admin');
        }
        $harness->clock->time += 800;

        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
    }

    public function testLogoutRequiresCsrfAndDestroysSession(): void
    {
        $harness = new Harness();
        $harness->signIn();

        self::assertSame(403, $harness->post('/admin/logout', [], false)->status);
        self::assertSame(200, $harness->get('/admin')->status);
        self::assertSame(303, $harness->post('/admin/logout', [])->status);
        self::assertSame(1, $harness->session->destructions);
        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
    }

    public function testTamperedRecordDoesNotBreakDashboard(): void
    {
        $harness = new Harness();
        $harness->submissions->save(new EncryptedSubmission('bad-id', 'x', 'y', 'z', '2026-01-01 00:00:00'));
        $harness->signIn();
        $response = $harness->get('/admin');

        self::assertSame(200, $response->status);
        self::assertStringContainsString('could not be decrypted', $response->body);
    }

    public function testRecordsCannotBeSwappedBetweenRows(): void
    {
        $harness = new Harness();
        $harness->post('/contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'message' => 'First', 'g-recaptcha-response' => 't']);
        $harness->post('/contact', ['name' => 'Bob', 'email' => 'bob@example.com', 'message' => 'Second', 'g-recaptcha-response' => 't']);
        [$first, $second] = $harness->submissions->records;
        $harness->submissions->records = [
            new EncryptedSubmission($first->id, $second->encryptedName, $first->encryptedEmail, $first->encryptedMessage, $first->createdAt),
            $second,
        ];
        $harness->signIn();

        self::assertStringContainsString('could not be decrypted', $harness->get('/admin')->body);
    }

    public function testBlockedAddressDoesNotConsumeAccountAttempts(): void
    {
        $harness = new Harness();
        for ($attempt = 0; $attempt < 10; ++$attempt) {
            $harness->post('/admin/login', ['username' => 'probe-' . $attempt, 'password' => 'wrong password value']);
        }
        $blocked = $harness->post('/admin/login', ['username' => Harness::USERNAME, 'password' => 'wrong password value']);

        self::assertSame(429, $blocked->status);
        self::assertArrayNotHasKey('login-account|' . Harness::USERNAME, $harness->rateLimiter->hits);
    }

    public function testRemovedAdminLosesActiveSession(): void
    {
        $harness = new Harness();
        $harness->signIn();
        self::assertSame(200, $harness->get('/admin')->status);
        $harness->admins->users = [];

        self::assertSame('/admin/login', $harness->get('/admin')->headers['Location']);
        self::assertNull($harness->session->get('admin_id'));
    }

    public function testPaginationIsClamped(): void
    {
        $harness = new Harness();
        $harness->signIn();

        self::assertStringContainsString('Page 1 of 1', $harness->get('/admin', ['page' => '999'])->body);
        self::assertStringContainsString('Page 1 of 1', $harness->get('/admin', ['page' => ['x']])->body);
    }
}
