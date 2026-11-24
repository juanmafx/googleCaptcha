# Security Audit

Scope: full review of the repository during the 2026 modernization, followed by a second review of the rebuilt code. Both are self-performed engineering reviews, not an independent penetration test.

## Findings in the original code, now resolved

| Severity | Finding | Resolution |
| --- | --- | --- |
| High | Reflected XSS through an echoed request parameter | The value is never rendered; all output is escaped; CSP blocks inline script |
| High | Unencoded user input concatenated into the verification URL | Form-encoded POST body built by `http_build_query` |
| Medium | Server secret placed in a URL query string | Sent in the POST body |
| Medium | Secret location hardcoded in source (placeholder value only) | Loaded from the environment |
| Medium | No CSRF protection | Session-bound tokens with constant-time comparison |
| Medium | No rate limiting | Database-backed limits on submission, login, and TOTP |
| Medium | Verification failures not handled | Fails closed on network, parsing, or schema errors |
| Low | Undefined variable when the token was missing | Replaced by validated control flow |
| Low | No security headers | CSP, HSTS, framing, referrer, and related headers |

No real credential or private key was found in the three-commit history. The old secret value was a placeholder.

## Findings in the rebuilt code, now resolved

Each code finding was reproduced first and has a regression test.

| Severity | Finding | Resolution |
| --- | --- | --- |
| Medium | Login timing revealed whether a username exists: unknown users cost two Argon2id operations, and over-length passwords returned immediately for known users | One verification against a precomputed decoy on every failure path |
| Medium | The TOTP code used during enrollment could be replayed at first login | Enrollment records the accepted step |
| Medium | IPv6 clients could evade rate limits by rotating addresses within their /64 | Limits group IPv6 by /64 and normalize IPv4-mapped addresses |
| Low | A rate-limited address could keep consuming an account's login attempts and adding counter rows | The account limit is evaluated only after the address limit passes |
| Low | A removed admin kept access until the session expired, up to 8 hours | Each authenticated request confirms the account exists |
| Low | The CSP blocked a background request made by the reCAPTCHA widget | `connect-src` allows the reCAPTCHA path on captcha pages only |
| Low | The documented setup truncated the database DSN when `.env` was sourced by a shell | The value is quoted, and the setup steps were corrected |
| Low | CI skipped Composer setup while depending on Composer | CI installs Composer 2 explicitly |

## Controls added

Argon2id hashing, TOTP with replay protection, XChaCha20-Poly1305 field encryption with record binding, CSRF protection, strict session handling, input validation, mail header injection protection, prepared statements, and generic error responses. Notification emails contain no submitter data.

## Dependency status

`composer audit --locked` reported no known advisories when rerun from a clean install on 2026-11-24 (UTC). Lifecycle scripts and Composer plugins are disabled during installation.

## Known limitations

| Area | Limitation | Mitigation |
| --- | --- | --- |
| Key management | No automated rotation of `APP_MASTER_KEY` | Store it in a secret manager and back it up |
| Account recovery | No TOTP recovery codes or reset flow | An operator with database access must re-provision the account |
| Availability | Per-account login limits allow temporary lockout of an admin | Restrict `/admin` by network |
| Proxies | Client IP is `REMOTE_ADDR` only | Configure the web server's trusted real-IP module |
| Third party | Google's script cannot be pinned with SRI because Google updates it | CSP limits script sources to reCAPTCHA paths |
| Encryption | Protects data at rest, not from a compromised running application that holds the key | Least-privilege hosting |

## Verification limits

- reCAPTCHA was exercised against Google's live service only with its published test keys, in six headless Chromium runs of the final code. Four completed: the widget was solved and the submission was stored encrypted. Two stopped before submission, one because the widget did not load within the wait and one because the checkbox click timed out; the cause was not determined. No CSP violation was logged in any run. Production keys and other browsers were not tested.
- Automated tests use a reCAPTCHA test double, and the unreachable-service path was verified to fail closed.
- Not exercised against a real SMTP server.
- Not deployed behind TLS and PHP-FPM. Production cookies and HSTS were checked on the PHP built-in server.
- The GitHub Actions workflow has not run on GitHub.
- No third-party penetration test has been performed.
