# Deployment

Production is deployed from `infra/dokploy/compose.yaml`. The stack contains the
Laravel web application, queue worker, scheduler, private MariaDB and Redis, and
a persistent RSA signing-key volume. Only the application receives a public
HTTPS domain; database, Redis, worker, scheduler, and signing-key storage remain
private.

Dokploy owns production environment values. Copy only the names from
`infra/dokploy/.env.example`; never commit their values. The one-time `key-init`
service creates a 4096-bit RSA key pair when the signing volume is empty. It
never replaces an existing key. The application uses:

```text
LICENSE_SIGNING_PRIVATE_KEY_PATH=/run/secrets/naxas-license/buildora-private.pem
LICENSE_SIGNING_KEY_ID=buildora-production-1
```

The private key, MariaDB data, and persistent application storage require daily
off-server backups. Restore the key and database together: replacing the key
without a deliberate rotation would invalidate previously issued signatures.

The web entrypoint runs additive migrations, the idempotent catalog seeder, and
Laravel optimization before Apache starts. Create the first administrator from
the running application container with `php artisan portal:create-admin`; do not
put an administrator password in Compose, CI, Git, or shell history.

Verify `/health`, container readiness, a real administrator login, activation
request creation, approval, signed status retrieval, acknowledgement/replay,
and an isolated backup restoration before treating a release as complete.

## Continuous delivery

The repository is connected to the self-hosted Woodpecker service at
`https://ci.naxaslimited.com`. Pull requests, pushes, and manually started
pipelines run the PHP test suite and production asset build. Only a successful
push to `master` can use the repository-scoped `dokploy_deploy_webhook` secret
to request a production deployment.

Dokploy's direct GitHub auto-deploy must remain disabled for this service. This
ensures a push cannot bypass the CI quality gate. The webhook is a scoped
deployment trigger, not a general Dokploy API credential, and must remain in
Woodpecker's secret store.
