#!/usr/bin/env sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

wait_for_database() {
    attempts=0
    until php -r '
        new PDO(
            "mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
            getenv("DB_USERNAME"),
            getenv("DB_PASSWORD"),
            [PDO::ATTR_TIMEOUT => 2],
        );
    ' >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge 60 ]; then
            echo "Database did not become ready in time." >&2
            exit 1
        fi
        sleep 2
    done
}

mode="${1:-web}"
case "$mode" in
    web)
        wait_for_database
        php artisan migrate --force
        php artisan db:seed --force
        php artisan storage:link >/dev/null 2>&1 || true
        php artisan optimize
        exec apache2-foreground
        ;;
    worker)
        wait_for_database
        exec php artisan queue:work redis --sleep=2 --tries=3 --timeout=90 --max-time=3600
        ;;
    scheduler)
        wait_for_database
        exec php artisan schedule:work
        ;;
    key-init)
        key_directory=/run/secrets/naxas-license
        private_key="$key_directory/buildora-private.pem"
        public_key="$key_directory/buildora-public.pem"
        mkdir -p "$key_directory"
        if [ ! -s "$private_key" ]; then
            umask 077
            openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:4096 -out "$private_key"
            openssl pkey -in "$private_key" -pubout -out "$public_key"
        fi
        chown 33:33 "$private_key" "$public_key"
        chmod 640 "$private_key"
        chmod 644 "$public_key"
        ;;
    *)
        exec "$@"
        ;;
esac
