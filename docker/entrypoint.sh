#!/bin/sh
set -e

# ============================================================================
#  Entrypoint de producción — community-server
#  - Espera a la base de datos
#  - Prepara storage:link, caches y migraciones
#  - Arranca supervisord (php-fpm + nginx + queue)
# ============================================================================

cd /var/www/html

# En producción la configuración llega por variables de entorno de Docker, pero
# algunas órdenes de artisan (key:generate) esperan un archivo .env. Creamos uno
# vacío si no existe para no romper esos comandos.
if [ ! -f .env ]; then
    echo "==> No existe .env, creando uno vacío..."
    : > .env
fi

echo "==> Esperando a la base de datos (${DB_HOST}:${DB_PORT})..."
ATTEMPTS=0
until php -r "exit(@fsockopen(getenv('DB_HOST'), (int)getenv('DB_PORT')) ? 0 : 1);" 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ "$ATTEMPTS" -ge 30 ]; then
        echo "!! La base de datos no respondió tras 30 intentos. Abortando."
        exit 1
    fi
    echo "   ...base de datos no disponible aún, reintentando ($ATTEMPTS/30)"
    sleep 2
done
echo "==> Base de datos disponible."

# Genera APP_KEY si no está definida en el entorno.
# key:generate escribe en el .env (que ya garantizamos que existe arriba) y ese
# valor lo toma Laravel al leer el archivo. Recomendado: definir APP_KEY fija en
# las variables del Stack/.env para que sea estable entre redeploys.
if [ -z "${APP_KEY}" ]; then
    echo "==> APP_KEY no definida, generando una en .env..."
    php artisan key:generate --force
fi

# Enlace simbólico para archivos públicos (avatares, imágenes de banner, etc.)
echo "==> Creando storage:link..."
php artisan storage:link || true

# Permisos de escritura
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Migraciones (idempotente). Usa SEED_ON_DEPLOY=true para sembrar la 1ª vez.
echo "==> Ejecutando migraciones..."
php artisan migrate --force

if [ "${SEED_ON_DEPLOY}" = "true" ]; then
    echo "==> Ejecutando seeders (SEED_ON_DEPLOY=true)..."
    php artisan db:seed --force || true
fi

# Cache de configuración/rutas/vistas para producción
echo "==> Optimizando cachés de Laravel..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Arranque completo. Cediendo el control a supervisord."
exec "$@"
