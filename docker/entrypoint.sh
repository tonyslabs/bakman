#!/bin/sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "Esperando a mariadb:3306..."
until php -r "new PDO('mysql:host=mariadb;port=3306', getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    sleep 2
done
echo "mariadb listo."

php artisan migrate --force

php artisan db:seed --class=Database\\Seeders\\AdminUserSeeder --force

SSH_DIR=$(dirname "${SSH_KEY_PATH:-/data/ssh/id_ed25519}")
mkdir -p "$SSH_DIR"
if [ ! -f "${SSH_KEY_PATH:-/data/ssh/id_ed25519}" ]; then
    echo "No hay clave SSH en el volumen, generando una (fallback, no debería pasar en un despliegue normal)..."
    ssh-keygen -t ed25519 -N '' -f "${SSH_KEY_PATH:-/data/ssh/id_ed25519}" -C backup-manager
fi
chmod 600 "${SSH_KEY_PATH:-/data/ssh/id_ed25519}"

php artisan config:cache
php artisan route:cache

exec "$@"
