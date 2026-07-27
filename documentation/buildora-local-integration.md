# Buildora local end-to-end acceptance (Windows / Laragon)

The portal is the repository root and the local Buildora CMS fixture is `naxas-license-portal\`. Both are versioned by the repository root. These instructions deliberately opt in to HTTP only for loopback hosts and only with `APP_ENV=local`. Never use these HTTP values in production.

## 1. Databases and disposable keys

Open **Laragon Terminal** in the repository root:

```bat
mysql -u root -e "CREATE DATABASE IF NOT EXISTS naxas_license_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS naxas_license_portal_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mkdir C:\laragon\etc\naxas-test-keys
openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -out C:\laragon\etc\naxas-test-keys\portal-private.pem
openssl pkey -in C:\laragon\etc\naxas-test-keys\portal-private.pem -pubout -out C:\laragon\etc\naxas-test-keys\buildora-public.pem
```

The private key stays outside both applications. Buildora receives only `buildora-public.pem`.

## 2. Portal configuration and start (port 8001)

```bat
copy .env.example .env
composer install
npm install
php artisan key:generate
```

Set these portal `.env` values:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8001
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=naxas_license_portal
DB_USERNAME=root
DB_PASSWORD=
LICENSE_ALLOW_LOCAL_HTTP=true
LICENSE_LOCAL_HTTP_HOSTS=127.0.0.1,localhost,::1,naxas-license-portal.test
LICENSE_SIGNING_PRIVATE_KEY_PATH=C:/laragon/etc/naxas-test-keys/portal-private.pem
LICENSE_SIGNING_KEY_ID=local-test-1
```

Then migrate, seed, create the administrator, build, and serve:

```bat
php artisan migrate --seed
php artisan portal:create-admin
npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

## 3. Buildora configuration and start (port 8000)

In a second Laragon Terminal:

```bat
cd naxas-license-portal
copy .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
npm run build
```

Set these Buildora `.env` values:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
LICENSE_PORTAL_URL=http://127.0.0.1:8001
LICENSE_ALLOW_LOCAL_HTTP=true
LICENSE_TRUSTED_LOCAL_HOSTS=127.0.0.1,localhost,::1,naxas-license-portal.test
LICENSE_PUBLIC_KEY_PATH=C:/laragon/etc/naxas-test-keys/buildora-public.pem
LICENSE_PORTAL_TIMEOUT_SECONDS=10
LICENSE_MAX_RESPONSE_BYTES=131072
```

Start Buildora:

```bat
php artisan serve --host=127.0.0.1 --port=8000
```

## 4. Acceptance flow

1. Sign in to Buildora and visit `http://127.0.0.1:8000/settings/license`.
2. Select **Generate Activation Request**. Confirm the page says **Pending** and copy the full `BRQ-...` token; it is shown only on this authenticated page.
3. Sign in to `http://127.0.0.1:8001/admin`, open **Customers**, and create the buyer.
4. Open **Licenses**, create a **Buildora CMS / Single Site** license, then open the pending activation request, select that license, and approve it.
5. Return to Buildora and select **Check Activation Status**. Buildora verifies RSA/SHA-256, product, type, UUID, domain, and expiry before encrypted persistence, acknowledges the matching fingerprint, and displays **Active** plus update/support state.
6. If the first approved response is interrupted, select **Check Activation Status** again. The portal returns the identical encrypted-at-rest entitlement throughout the bounded delivery window and does not complete the request until the proof-bound fingerprint acknowledgement succeeds.

## 5. Testing database, rate limits, and troubleshooting

Create `.env.testing` with `DB_DATABASE=naxas_license_portal_testing`; verify the database name before resetting it:

```bat
php artisan about --env=testing
php artisan migrate:fresh --seed --env=testing --force
php artisan test --compact
```

Never run `migrate:fresh` against `naxas_license_portal`. Local limits can be raised without removing throttling:

```dotenv
LICENSE_CREATE_RATE_LIMIT=100
LICENSE_STATUS_RATE_LIMIT=500
PORTAL_SUBMIT_RATE_LIMIT=100
```

After `.env` changes run `php artisan optimize:clear`. Troubleshoot without exposing tokens or signed licenses:

```bat
php artisan route:list --except-vendor
powershell -Command "Get-Content storage\logs\laravel.log -Tail 100"
cd naxas-license-portal
powershell -Command "Get-Content storage\logs\laravel.log -Tail 100"
```

For a clean Buildora retry, delete only its local `license_states` row with Tinker; do not paste secrets into shell history:

```bat
php artisan tinker
```

Then run `App\Models\LicenseState::query()->delete();` inside Tinker.
