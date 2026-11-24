# googleCaptcha

A hardened PHP contact form protected by Google reCAPTCHA v2, with encrypted storage and an admin inbox behind password plus TOTP two-factor authentication.

> **Project history:** originally created in 2019 as a two-file reCAPTCHA example, modernized in 2026.

## Purpose

The project shows how to take the smallest possible reCAPTCHA integration and turn it into a contact form that is safe to expose to the internet. Submissions are personal data, so they are validated, rate limited, encrypted before they reach the database, and readable only by an authenticated administrator.

## Why this project exists

The original 2019 code verified a reCAPTCHA token and printed the result. It also reflected user input into the page and placed the server secret in a URL. This version keeps the same idea and addresses those problems with a small, testable codebase and no full-stack framework.

## What it does

- Serves a contact form with name, email, message, and a reCAPTCHA v2 checkbox.
- Verifies the captcha server-side and fails closed when Google cannot be reached.
- Encrypts name, email, and message with XChaCha20-Poly1305 before storage.
- Sends a notification email that contains only a reference ID, never the submitter's data.
- Provides an admin inbox at `/admin` that requires an Argon2id-hashed password and a TOTP code.

## Architecture

```text
frontend/            HTML templates and stylesheet
backend/
  bin/               Administrative CLI
  public/            Web root and front controller
  src/
    Admin/           Admin accounts and authentication
    Captcha/         reCAPTCHA verification
    Cli/             Command logic
    Config/          Environment validation
    Contact/         Validation and submission use cases
    Http/            Routing, controllers, sessions, views, headers
    Mail/            Notification delivery
    Persistence/     PDO repositories and rate limiter
    Security/        Encryption, hashing, TOTP, CSRF
    Support/         Clock abstraction
  tests/             Unit, functional, and integration suites
db/schema.sql        MySQL schema
docs/                Architecture and security audit
reports/tests/       Committed results of real test runs
```

See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) for request flows and design decisions.

## Quick start

Requirements: PHP 8.4 or newer with `curl`, `mbstring`, `pdo_mysql`, and `sodium`; Composer; MySQL 8.4; and a [reCAPTCHA v2 key pair](https://www.google.com/recaptcha/admin).

```bash
composer install --no-scripts --no-plugins
cp .env.example .env
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
```

Put the generated value in `APP_MASTER_KEY`, fill in the remaining values, then load the schema and start the app:

```bash
set -a; . ./.env; set +a
mysql -h 127.0.0.1 -u "$DATABASE_USER" -p secure_contact < db/schema.sql
php backend/bin/create-admin.php site-admin
php -S 127.0.0.1:8080 -t backend/public backend/public/index.php
```

The PHP built-in server is for local development only.

## Usage guide

1. Open `http://127.0.0.1:8080/`, complete the form and the captcha, and submit.
2. `create-admin.php` asks for a password of at least 12 bytes, prints an `otpauth://` URI for your authenticator app, and requires a valid code before it creates the account. Treat that URI as a secret.
3. Sign in at `/admin/login`, enter the current TOTP code, and read submissions at `/admin`.

## Configuration

| Variable | Required | Description |
| --- | --- | --- |
| `APP_ENV` | No | `production` (default) or `development`. Production enables Secure `__Host-` cookies and HSTS, so it requires HTTPS. |
| `APP_MASTER_KEY` | Yes | Base64 of 32 random bytes. Encryption subkeys are derived from it. |
| `RECAPTCHA_SITE_KEY` | Yes | Public site key. |
| `RECAPTCHA_SECRET_KEY` | Yes | Server-side secret. |
| `RECAPTCHA_EXPECTED_HOSTNAME` | No | Rejects tokens solved on any other hostname. Recommended in production. |
| `DATABASE_DSN` | Yes | PDO MySQL DSN. |
| `DATABASE_USER` | Yes | Database user. |
| `DATABASE_PASSWORD` | No | Database password. |
| `MAILER_DSN` | Yes | [Symfony Mailer DSN](https://symfony.com/doc/current/mailer.html). `null://null` disables delivery. |
| `MAIL_FROM` | Yes | Notification sender. |
| `MAIL_TO` | Yes | Notification recipient. |

**Losing `APP_MASTER_KEY` makes stored submissions and TOTP secrets unrecoverable.** Back it up separately from the database.

## Testing

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
```

Running PHPUnit also needs the `dom`, `xml`, and `xmlwriter` extensions. Integration tests use a real MySQL database, **drop and recreate this project's three tables**, and are skipped unless these variables are set, so point them only at a disposable database:

```bash
TEST_DATABASE_DSN='mysql:host=127.0.0.1;dbname=contact_test;charset=utf8mb4' \
TEST_DATABASE_USER=contact_test TEST_DATABASE_PASSWORD=change-me vendor/bin/phpunit
```

Results of the latest recorded run are in [reports/tests/](reports/tests/). Automated tests replace Google and the mail server with test doubles; see the limitations in the [security audit](docs/SECURITY_AUDIT.md).

## Security notes

- Argon2id password hashing (64 MiB, 3 iterations) with per-password salts.
- TOTP second factor with replay protection and rate limiting.
- Authenticated field encryption bound to each record and field.
- CSRF tokens, strict session cookies, session rotation, and idle and absolute timeouts.
- Strict Content Security Policy with no inline scripts or styles.
- Prepared statements, output escaping, and rejection of control characters in header-bound fields.

Read [SECURITY.md](SECURITY.md) to report a vulnerability and [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md) for the audit and known limitations.

## Credits and license

reCAPTCHA is a Google service. Runtime libraries include [Symfony Mailer](https://github.com/symfony/mailer) and [OTPHP](https://github.com/Spomky-Labs/otphp), each under its own license; the full list is in `composer.lock`.

This repository does not currently declare an open-source license.
