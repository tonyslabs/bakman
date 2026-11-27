#!/bin/sh
set -e

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

DB_WAIT_HOST="${DB_HOST:-mariadb}"
DB_WAIT_PORT="${DB_PORT:-3306}"
echo "Esperando a la base de datos en ${DB_WAIT_HOST}:${DB_WAIT_PORT}..."
tries=0
until php -r "new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -ge 30 ]; then
        echo "ERROR: no se pudo conectar a ${DB_WAIT_HOST}:${DB_WAIT_PORT} con DB_USERNAME/DB_PASSWORD. Revisa DB_HOST, las credenciales y que el contenedor vea esa red." >&2
        exit 1
    fi
    sleep 2
done
echo "Base de datos lista."

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
