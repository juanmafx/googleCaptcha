# Security Audit

Scope: full review of the repository during the 2026 modernization. This is a self-performed engineering review, not an independent penetration test.

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

## Controls added

Argon2id hashing, TOTP with replay protection, XChaCha20-Poly1305 field encryption with record binding, CSRF protection, strict session handling, input validation, mail header injection protection, prepared statements, and generic error responses. Notification emails contain no submitter data.

## Dependency status

`composer audit --locked` reported no known advisories on 2026-11-24 (UTC). Lifecycle scripts and Composer plugins are disabled during installation.

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

- Not exercised against the live Google reCAPTCHA service; success paths use a test double, and the unreachable-service path was verified to fail closed.
- Not exercised against a real SMTP server.
- The CSP was not checked in a real browser with a live reCAPTCHA widget. Verify the widget renders before deploying.
- No third-party penetration test has been performed.
