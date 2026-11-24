# Architecture

## Overview

The application is a server-rendered PHP monolith with explicit boundaries. It uses two focused runtime libraries, Symfony Mailer and OTPHP, instead of a full-stack framework. `frontend/` holds presentation; `backend/` holds all logic.

```text
Browser ──> backend/public/index.php ──> Application (router + security headers)
                                            ├── ContactController ──> ContactService ──> SubmissionRepository ──> MySQL
                                            │        ├── CsrfGuard          └── SubmissionNotifier ──> SMTP
                                            │        ├── RateLimiter
                                            │        └── CaptchaVerifier ──> Google siteverify
                                            └── AdminController ──> AdminAuthenticator ──> AdminRepository ──> MySQL
```

## Module structure

| Module | Responsibility | Depends on |
| --- | --- | --- |
| `Http` | Requests, responses, routing, controllers, sessions, templates | All use-case modules |
| `Contact` | Validation and the submit and read use cases | `Security`, `Mail`, `Support` |
| `Admin` | Credential and second-factor verification | `Security` |
| `Security` | Encryption, key derivation, hashing, TOTP, CSRF | `Support`, session contract |
| `Captcha` | reCAPTCHA verification behind an HTTP contract | None |
| `Mail` | Notification delivery | Symfony Mailer |
| `Persistence` | PDO implementations of repository contracts | `Admin`, `Contact`, `Security` |
| `Config` | Validates the environment at startup | None |
| `Cli` | Admin provisioning | `Admin`, `Security` |

Use cases depend on interfaces such as `SubmissionRepository`, `RateLimiter`, and `CaptchaVerifier`, so the functional suite exercises the full HTTP flow without network access.

## Request flows

### Contact submission

1. Validate the CSRF token.
2. Apply the per-address rate limit of 5 submissions per 15 minutes. IPv6 clients are grouped by /64 prefix.
3. Validate input, rejecting control characters in single-line fields.
4. Verify the captcha server-side, failing closed.
5. Encrypt each field with a context of `submission:<id>:<field>`.
6. Store the record, send an ID-only notification, and redirect with `303`.

### Admin authentication

1. Password step: CSRF, a rate limit per address, then per account, so a blocked address stops consuming the account's attempts. Unknown users and over-length passwords perform one Argon2id verification against a decoy hash, matching the cost of a real check.
2. The session is rotated and marked pending for at most 5 minutes. A pending session cannot read data.
3. TOTP step: CSRF, 5 attempts per 5 minutes, and an accepted window of one step either side.
4. The accepted step is advanced atomically in the database, so a code cannot be reused.
5. The session is rotated again, with a 15-minute idle timeout and an 8-hour absolute lifetime. Each authenticated request confirms the account still exists.

## Data model

Defined in [`db/schema.sql`](../db/schema.sql).

- `contact_submissions`: UUID, three ciphertext columns, and a UTC timestamp.
- `admin_users`: username, Argon2id hash, encrypted TOTP secret, and the last accepted TOTP step, which starts at the enrollment code's step.
- `rate_limit_counters`: keyed by an HMAC of the bucket and subject, so client addresses are not stored in clear text.

## Key management

`APP_MASTER_KEY` feeds libsodium's KDF to derive separate subkeys for submissions, TOTP secrets, and rate-limit hashing. Ciphertexts carry a format version byte. Automated key rotation is not implemented.

## Recommended production deployment

```text
Internet ──> TLS reverse proxy ──> PHP-FPM (backend/public only) ──> Private MySQL
                                        └──> Authenticated SMTP relay
```

- Expose only `backend/public` as the web root.
- Inject secrets through the platform's secret store, and keep `APP_ENV=production`. PHP-FPM needs `clear_env = no` or explicit `env[...]` entries.
- `APP_ENV=production` sends HSTS with `includeSubDomains`, so confirm every subdomain serves HTTPS first.
- Grant the application's database user only `SELECT`, `INSERT`, `UPDATE`, and `DELETE`, and apply the schema with a separate administrative account.
- Restrict `/admin` by network or VPN where possible.
- Behind a proxy, configure the web server's trusted real-IP module. The app deliberately reads only `REMOTE_ADDR` and ignores spoofable forwarding headers, so without this every client shares one rate-limit bucket.
