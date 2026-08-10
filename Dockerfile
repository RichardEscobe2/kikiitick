# syntax=docker/dockerfile:1

# ============================================================================
# ETAPA 1 — Compilación de assets del frontend (Vue 3 + Vite + Tailwind 4)
# Nota: laravel-vite-plugin/fonts (bunny()) descarga "Instrument Sans" desde
# la CDN de Bunny Fonts EN TIEMPO DE BUILD — esta etapa requiere acceso a
# internet durante `docker build` (normal en runners de CI/CD con salida a
# internet; fallaría en un build completamente air-gapped).
# ============================================================================
FROM node:20-alpine AS frontend-build
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY resources/ resources/
COPY vite.config.js ./

RUN npm run build

# ============================================================================
# ETAPA 2 — Dependencias de Composer (autoload de producción, sin dev)
# ============================================================================
FROM composer:2 AS composer-build
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-progress \
        --prefer-dist \
        --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative

# ============================================================================
# ETAPA 3 — Imagen final: PHP 8.3-FPM (Alpine)
# ============================================================================
FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

# 🛡️ Extensiones determinadas por auditoría real del repositorio (composer.lock
# + grep de app/), NO una lista genérica de plantilla:
#   - pdo_mysql  -> requerido: DB_CONNECTION=mysql (config/database.php)
#   - fileinfo   -> requerido: regla de validación 'image'/'mimes:' en
#                   EventoController::store()/update() (sube imagen del evento
#                   a Storage::disk('public'), Laravel usa finfo para el MIME real)
#   - zip        -> Composer lo usa para descomprimir paquetes; sin ella cae a un
#                   descompresor en PHP puro más lento, pero funciona
#   - bcmath     -> no se usa hoy en app/ (grep sin resultados) — se incluye de
#                   forma defensiva por ser una app que maneja montos monetarios,
#                   costo casi nulo. intl NO se incluye: sin evidencia de uso.
#   - gd         -> 🆕 agregada en esta iteración, NO en la auditoría original:
#                   ConfirmacionCompraMail::generarQrDataUri() ahora genera el QR
#                   de cada boleto server-side (endroid/qr-code, PngWriter) para
#                   embeberlo en el correo de confirmación — los QR de la SPA
#                   siguen siendo 100% client-side (qrcode/jsbarcode en Vue), esto
#                   es exclusivamente para que el QR también aparezca dentro del
#                   correo mismo, donde no hay JS que lo genere. Antes de esta
#                   tarea no había NINGÚN uso server-side de generación de
#                   imágenes, por eso se había excluido deliberadamente.
#   - opcache    -> rendimiento en producción (requerido por la tarea)
#   - pcntl      -> manejo correcto de señales (SIGTERM) en el worker de colas
#                   `php artisan queue:work` del servicio queue-worker
# 🛡️🐛 Encontrado con un `docker build` real durante la validación del Módulo 6:
# purgar solo los paquetes "-dev" también arrastra la librería compartida en
# TIEMPO DE EJECUCIÓN (ej. libzip.so.5) como dependencia huérfana de apk — apk
# no tiene forma de saber que el .so ya compilado de PHP la sigue necesitando,
# porque esa dependencia nunca quedó registrada como paquete apk. Por eso
# "libzip" y "libpng" se declaran explícitamente, para que sobrevivan la purga
# de los -dev correspondientes.
RUN apk add --no-cache \
        libzip \
        libzip-dev \
        libpng \
        libpng-dev \
        oniguruma-dev \
        netcat-openbsd \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        fileinfo \
        zip \
        bcmath \
        gd \
        opcache \
        pcntl \
    && apk del --no-cache libzip-dev libpng-dev oniguruma-dev

# Configuración de OPcache para producción (validate_timestamps=0: exige que
# cada despliegue reconstruya la imagen o reinicie el contenedor — no recarga
# cambios de archivo en caliente, a cambio de máximo rendimiento).
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Código de la aplicación (autoload de Composer ya optimizado en la etapa 2)
COPY --from=composer-build /app ./
# Assets compilados de Vite (manifest.json + JS/CSS con hash) — @vite() en
# welcome.blade.php los resuelve leyendo public/build/manifest.json
COPY --from=frontend-build /app/public/build ./public/build

# 🛡️🐛 Encontrado con un `docker build` real durante la validación de esta
# tarea: public/ se deja intencionalmente de solo lectura para www-data (ver
# el bloque de permisos de abajo — public/ es lo que Nginx sirve directo, un
# docroot escribible por el proceso PHP es una superficie de ataque innecesaria
# ante cualquier RCE). Pero eso significa que `php artisan storage:link` NUNCA
# puede correr en tiempo de ejecución como www-data (falla con "symlink():
# Permission denied", confirmado). Se crea aquí, en build time, como root,
# ANTES del chown/USER de abajo — symlink RELATIVO (no vía artisan, para no
# depender del bootstrap de Laravel durante el build) para que se resuelva
# igual en cualquier contenedor que monte los mismos volúmenes en las mismas
# rutas (ver docker-compose.yml). No es funcionalmente necesario para servir
# imágenes (Nginx usa `alias` directo a storage/app/public, ver
# docker/nginx/default.conf) — se deja solo por compatibilidad con cualquier
# código que verifique la existencia del symlink.
RUN ln -sfn ../storage/app/public /var/www/html/public/storage

# 🛡️ Permisos: solo storage/ y bootstrap/cache/ deben ser escribibles por el
# proceso PHP (logs, cache de vistas/rutas, sesiones de archivo) — el resto del
# código de la aplicación permanece de solo lectura para el usuario www-data.
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

# Healthcheck: confirma que php-fpm está escuchando en el puerto FastCGI —
# no valida que Laravel arrancó correctamente (eso lo cubre el healthcheck
# HTTP de `web`, que sí atraviesa el stack completo vía Nginx).
HEALTHCHECK --interval=30s --timeout=3s --start-period=20s --retries=3 \
    CMD nc -z 127.0.0.1 9000 || exit 1

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
