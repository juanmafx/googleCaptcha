<?php

declare(strict_types=1);

namespace SecureContact\Tests\Support;

use OTPHP\TOTP;
use SecureContact\Admin\AdminAuthenticator;
use SecureContact\Contact\ContactService;
use SecureContact\Contact\ContactValidator;
use SecureContact\Http\AdminController;
use SecureContact\Http\Application;
use SecureContact\Http\ContactController;
use SecureContact\Http\Request;
use SecureContact\Http\Response;
use SecureContact\Http\View;
use SecureContact\Security\CsrfGuard;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;

final class Harness
{
    public const string USERNAME = 'site-admin';
    public const string PASSWORD = 'correct horse battery staple';

    public ArraySession $session;
    public FixedClock $clock;
    public InMemoryRateLimiter $rateLimiter;
    public InMemorySubmissions $submissions;
    public InMemoryAdmins $admins;
    public RecordingNotifier $notifier;
    public StubCaptcha $captcha;
    public FieldCipher $submissionCipher;
    public PasswordHasher $hasher;
    public string $totpSecret;
    public Application $application;

    public function __construct()
    {
        $root = dirname(__DIR__, 3);
        $this->session = new ArraySession();
        $this->clock = new FixedClock();
        $this->rateLimiter = new InMemoryRateLimiter();
        $this->submissions = new InMemorySubmissions();
        $this->admins = new InMemoryAdmins();
        $this->notifier = new RecordingNotifier();
        $this->captcha = new StubCaptcha();
        $this->submissionCipher = new FieldCipher(random_bytes(32));
        $this->hasher = new PasswordHasher(['memory_cost' => 8192, 'time_cost' => 1, 'threads' => 1]);
        $totpCipher = new FieldCipher(random_bytes(32));
        $totp = new TotpService($this->clock);
        $this->totpSecret = $totp->generateSecret();
        $this->admins->create(
            self::USERNAME,
            $this->hasher->hash(self::PASSWORD),
            $totpCipher->encrypt($this->totpSecret, AdminAuthenticator::totpContext(self::USERNAME)),
        );

        $view = new View($root . '/frontend/templates');
        $csrf = new CsrfGuard($this->session);
        $contacts = new ContactService($this->submissions, $this->submissionCipher, $this->notifier, $this->clock);
        $authenticator = new AdminAuthenticator($this->admins, $this->hasher, $totp, $totpCipher);
        $this->application = new Application(
            new ContactController($view, $csrf, $this->rateLimiter, $this->captcha, new ContactValidator(), $contacts, 'public-site-key'),
            new AdminController($view, $this->session, $csrf, $this->rateLimiter, $authenticator, $contacts, $this->clock),
            $view,
            $root . '/frontend/assets/app.css',
            true,
        );
    }

    public function get(string $path, array $query = []): Response
    {
        return $this->application->handle(new Request('GET', $path, $query, [], '203.0.113.10'));
    }

    public function post(string $path, array $fields, bool $withCsrf = true): Response
    {
        if ($withCsrf) {
            $fields['csrf_token'] = (new CsrfGuard($this->session))->token();
        }

        return $this->application->handle(new Request('POST', $path, [], $fields, '203.0.113.10'));
    }

    public function currentCode(int $offsetSteps = 0): string
    {
        return TOTP::createFromSecret($this->totpSecret)->at($this->clock->time + $offsetSteps * 30);
    }

    public function signIn(): void
    {
        $this->post('/admin/login', ['username' => self::USERNAME, 'password' => self::PASSWORD]);
        $this->post('/admin/two-factor', ['code' => $this->currentCode()]);
    }
}
