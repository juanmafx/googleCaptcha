# Changelog

This project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

### Added

- Contact form with name, email, message, validation, and reCAPTCHA v2.
- Encrypted submission storage with XChaCha20-Poly1305.
- Admin inbox with Argon2id passwords and TOTP two-factor authentication.
- CSRF protection, rate limiting, security headers, and hardened sessions.
- Notification email through Symfony Mailer.
- MySQL schema, admin provisioning CLI, automated tests, static analysis, and CI.
- README, architecture, security policy, audit, and release documentation.

### Changed

- Restructured the project into `frontend/` and `backend/`.
- All configuration now comes from environment variables.
- All user-facing text is in English.

### Removed

- **Breaking:** `index.php` and `result.php`. The form is now at `/` and submits to `/contact`.

### Security

- Fixed reflected XSS, unencoded verification parameters, and exposure of the secret in a URL.
- Equalized login timing, blocked replay of the enrollment TOTP code, grouped IPv6 rate limits by /64, and ended sessions of removed admins.

## Pre-1.0 (2019)

- Unversioned two-file reCAPTCHA example.
