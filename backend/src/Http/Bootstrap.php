<?php

declare(strict_types=1);

namespace SecureContact\Http;

use SecureContact\Admin\AdminAuthenticator;
use SecureContact\Captcha\CurlHttpPoster;
use SecureContact\Captcha\RecaptchaVerifier;
use SecureContact\Config\AppConfig;
use SecureContact\Contact\ContactService;
use SecureContact\Contact\ContactValidator;
use SecureContact\Mail\SymfonySubmissionNotifier;
use SecureContact\Persistence\ConnectionProvider;
use SecureContact\Persistence\PdoAdminRepository;
use SecureContact\Persistence\PdoRateLimiter;
use SecureContact\Persistence\PdoSubmissionRepository;
use SecureContact\Security\CsrfGuard;
use SecureContact\Security\FieldCipher;
use SecureContact\Security\KeyRing;
use SecureContact\Security\PasswordHasher;
use SecureContact\Security\TotpService;
use SecureContact\Support\SystemClock;
use Symfony\Component\Mailer\Transport;

final class Bootstrap
{
    public static function application(AppConfig $config, string $projectRoot): Application
    {
        $clock = new SystemClock();
        $keys = new KeyRing($config->masterKey);
        $connections = new ConnectionProvider($config->databaseDsn, $config->databaseUser, $config->databasePassword);
        $session = new NativeSession($config->production);
        $csrf = new CsrfGuard($session);
        $view = new View($projectRoot . '/frontend/templates');
        $rateLimiter = new PdoRateLimiter($connections, $keys->rateLimitKey(), $clock);
        $contacts = new ContactService(
            new PdoSubmissionRepository($connections),
            new FieldCipher($keys->submissionKey()),
            new SymfonySubmissionNotifier(Transport::fromDsn($config->mailerDsn), $config->mailFrom, $config->mailTo),
            $clock,
        );
        $authenticator = new AdminAuthenticator(
            new PdoAdminRepository($connections, $clock),
            new PasswordHasher(),
            new TotpService($clock),
            new FieldCipher($keys->totpKey()),
        );

        return new Application(
            new ContactController(
                $view,
                $csrf,
                $rateLimiter,
                new RecaptchaVerifier(new CurlHttpPoster(), $config->recaptchaSecret, $config->recaptchaHostname),
                new ContactValidator(),
                $contacts,
                $config->recaptchaSiteKey,
            ),
            new AdminController($view, $session, $csrf, $rateLimiter, $authenticator, $contacts, $clock),
            $view,
            $projectRoot . '/frontend/assets/app.css',
            $config->production,
        );
    }
}
