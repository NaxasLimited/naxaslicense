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
| Repository paths | Portal: repository root; Buildora fixture: `naxas-license-portal/`; both belong to root Git branch `work` |
| Database / OpenSSL | MySQL client unavailable in the acceptance container; OpenSSL 3.0.13 |
| Dependency recovery | Both Composer manifests initially had stale content hashes. Both npm locks failed `npm ci` for missing optional WASM peers. Network proxy returned HTTP 403 for Packagist/GitHub, preventing vendor installation. |
| Buildora verifier | Implemented in the nested Buildora application using detached `base64url(payload).base64url(RSA-SHA256 signature)` and an external public key |
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

## Contract impact matrix

| Area | Portal behavior | Buildora behavior | Mismatch found | Risk | Required correction | Regression risk | Required test |
|---|---|---|---|---|---|---|---|
| Transport | API previously accepted HTTP everywhere | No request client existed | No environment/host boundary | Remote plaintext proof disclosure | Explicit local/testing opt-in plus trusted host allowlist on both sides | Local setup failure | HTTP policy tests |
| Create request | Returned the five documented fields | No client or secure proof store existed | End-to-end generation absent | Activation impossible/token leakage | Strict client schema and encrypted model casts | Existing CMS settings | Creation/UI test |
| Status/delivery | Changed approved to completed while forming first response | No poller existed | Interrupted response permanently lost entitlement | Buyer cannot activate | Repeat identical approved token until acknowledgement | Longer token retention | Interrupted response/retry test |
| Acknowledgement | Endpoint absent | Endpoint absent | No evidence of local verification/persistence | False completion | Proof + UUID + SHA-256 token fingerprint, transactional/idempotent completion | State transition race | Wrong and repeated acknowledgement tests |
| Signature | RSA/SHA-256 detached envelope | Verifier absent | No local trust decision | Forged/wrong-site activation | Exact-byte RSA verification; product/type/UUID/domain/expiry binding | Key deployment error | Tamper/wrong key/domain tests |
| Domain | Stored hostname; canonicalized `www` for entitlement | No normalization | Potential `www` disagreement | Valid buyer rejected | Equivalent lowercase/trimmed hostname rules | Edge-case IDNs | Domain binding tests |
| Installation | UUID proof checked at status | No persistent UUID | Requests could drift between installs | License replay | Unique persistent installation UUID | Existing installs need initialization | Wrong UUID test |
| Secrets/logging | Request token hashed and entitlement encrypted | No persistence policy | Client proof handling undefined | Credentials in database/logs | Encrypted casts and safe fixed messages; no secret logging | APP_KEY rotation | Storage/log redaction test |
| UI | Admin approval existed | License UI absent | No operator workflow | Cannot accept locally | Responsive authenticated state UI and double-submit controls | Theme integration | Desktop/mobile acceptance |
