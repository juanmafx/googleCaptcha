# Release Notes: 1.0.0

Status: prepared, not tagged or published.

The first versioned release turns the 2019 two-file reCAPTCHA example into a hardened contact form with encrypted storage and a two-factor admin inbox.

## Highlights

- Submissions are encrypted before reaching the database.
- The admin area requires an Argon2id-protected password and a TOTP code.
- CSRF protection, rate limiting, a strict CSP, and mail header injection protection.
- Automated test suite, static analysis, and a least-privilege CI workflow.

## Upgrade notes

This release is a breaking rewrite.

- Requires PHP 8.4 or newer and MySQL 8.4.
- Set the variables in `.env.example` and load `db/schema.sql`.
- Point the web root at `backend/public`.
- Create an administrator with `php backend/bin/create-admin.php <username>`.
- Requests to `index.php` and `result.php` no longer work.

## Before deploying

Confirm that the reCAPTCHA widget renders under the CSP and that mail delivery works with your provider. Neither was tested against live services. See [docs/SECURITY_AUDIT.md](docs/SECURITY_AUDIT.md).
