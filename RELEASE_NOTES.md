# Release Notes: 1.0.0

The first versioned release turns the 2019 two-file reCAPTCHA example into a hardened contact form with encrypted storage and a two-factor admin inbox.

## Highlights

- Submissions are encrypted before reaching the database.
- The admin area requires an Argon2id-protected password and a TOTP code.
- CSRF protection, rate limiting, a strict CSP, and mail header injection protection.
- Automated test suite, static analysis, and a least-privilege CI workflow.

## Upgrade notes

This release is a breaking rewrite.

- Requires PHP 8.4.1 or newer with Argon2 support, and MySQL 8.4.
- Set the variables in `.env.example` and load `db/schema.sql`.
- Point the web root at `backend/public`.
- Create an administrator with `php backend/bin/create-admin.php <username>`.
- Requests to `index.php` and `result.php` no longer work.

## Before deploying

Confirm that the reCAPTCHA widget works with your production keys and that mail delivery works with your provider. The widget was tested only with Google's published test keys in headless Chromium, where four of six runs completed, and mail delivery was not tested against a real server. See [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md).
