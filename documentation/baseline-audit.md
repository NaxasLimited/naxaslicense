# Pre-change baseline audit (2026-07-27)

| Item | Recorded baseline |
|---|---|
| Laravel | Composer constraint `^13.17`; lock resolved 13.x (artisan unavailable before dependency installation) |
| PHP / Composer | PHP 8.5.7-dev; Composer 2.9.7 |
| Node / npm | Node 24.15.0; npm 11.4.2 |
| Starter kit | Official Laravel Livewire starter kit using Fortify, Livewire 4 and Flux |
| Authentication routes | Login, logout, password reset/confirmation, email verification, 2FA and passkeys; registration was enabled |
| Public registration | Enabled via `Features::registration()` |
| Migrations | users, cache, jobs, passkeys, user 2FA columns |
| Tests | Auth (authentication, registration, reset, confirmation, verification, 2FA), settings, dashboard, examples |
| Defaults | Database session, cache, and queue drivers |
| Route baseline | `/`, `/dashboard`, settings, Fortify auth; exact artisan route output unavailable before vendor install |
| Dependency licenses | Root MIT; locked dependencies predominantly MIT/BSD/Apache/ISC; `composer licenses` initially unavailable because vendor was absent |
| Buildora request | Seven fields: product, version, license_type, installation_uuid, domain, environment, nonce; two documented POST endpoints |
| Buildora verifier | Cross-repository verifier unavailable. Contract supplied requires detached `base64url(canonical JSON).base64url(RSA-SHA256 signature)` |
| Canonicalization / RSA | Recursive bytewise object-key sort, list order retained, unescaped Unicode/slashes, exact UTF-8; RSA SHA-256/RS256 |
| Domain | Lowercase hostname, strip URL components/default port/trailing dot; bare/www equivalent; localhost, loopback, `.test`, `.local` non-production |
| Installation UUID | RFC-valid UUID required and proof must exactly match the stored request UUID |

## Impact and threat matrix

| Module | Responsibility | Exposure | Sensitive data | Abuse / failure | Controls | Required tests |
|---|---|---|---|---|---|---|
| Authentication | Admin identity | Public/admin | password, 2FA | takeover/inactive login | Fortify, throttling, active/admin gates | registration and access denial |
| Catalog | Product entitlements | Admin/API read | none | wrong edition | active lookup, FK constraints | unknown product |
| Customers/licenses | Manual issuance | Admin | PII, entitlement | IDOR, over-issuance | auth/admin, validation, non-public IDs | authorization, revoked license |
| Request API | Intake/proof creation | Public API | domain, UUID, proof | flood, injection, token disclosure | strict allowlist, 8 KiB cap, hashing, limits | validation, storage/log secrecy |
| Status API | Entitlement delivery | Public API | signed entitlement | brute force/replay | UUID + constant-time token proof, limits, terminal states | wrong proof, replay |
| Approval | Bind and sign | Admin | PII/entitlement | double bind/race | transaction, row locks, eligibility/domain checks | double approval, domain capacity |
| Signing | RS256 detached token | Internal | private key | key leak/weak key/bad bytes | external regular non-symlink RSA >=3072, canonical JSON | signature/tamper/key safety |
| Audit | Evidence | Admin-only future UI | safe identifiers | secret logging/deletion | allowlisted summaries, restrictive/nulling FKs | secret absence |
| Buyer portal | Human review status | Public web | request proof | enumeration/CSRF | safe state, CSRF, throttling, no auto-approval | CSRF/info disclosure |
| Health | Liveness | Public | none | reconnaissance | static minimal response | response schema |
