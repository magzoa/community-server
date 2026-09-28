# ============================================================================
#  community-server (Laravel 12 / PHP 8.2) — Imagen de producción
#  Nginx + PHP-FPM en un solo contenedor. Base MariaDB en servicio aparte.
# ============================================================================
FROM php:8.2-fpm-bookworm AS base

ENV DEBIAN_FRONTEND=noninteractive

# ---------------------------------------------------------------------------
# Dependencias del sistema + extensiones PHP requeridas por Laravel/Sanctum/Spatie
# ---------------------------------------------------------------------------
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        git \
        unzip \
        curl \
        ca-certificates \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        libxml2-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        mbstring \
        bcmath \
        zip \
        gd \
        exif \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer (copiado desde la imagen oficial)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ---------------------------------------------------------------------------
# Instalar dependencias PHP primero (aprovecha la caché de capas de Docker)
# ---------------------------------------------------------------------------
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ---------------------------------------------------------------------------
# Copiar el resto del proyecto y generar el autoloader optimizado
# ---------------------------------------------------------------------------
COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ---------------------------------------------------------------------------
# Configuración de Nginx, PHP-FPM, Supervisor y script de arranque
# ---------------------------------------------------------------------------
COPY docker/nginx.conf         /etc/nginx/sites-available/default
COPY docker/supervisord.conf   /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh      /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
