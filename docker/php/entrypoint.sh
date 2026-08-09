#!/bin/sh
set -e

# 🛡️ Deliberadamente NO se ejecuta `php artisan migrate --force` aquí. Auto-migrar
# en cada arranque de contenedor es cómodo pero riesgoso: si alguna vez se escala
# `app` a más de una réplica, dos contenedores arrancando a la vez correrían
# migraciones en paralelo sin coordinación. Migrar es un paso explícito y manual
# (o de CI/CD), documentado en el reporte de esta tarea — no vive en el entrypoint.

# 🛡️🐛 El symlink public/storage YA se crea en build time (Dockerfile, como
# root) — NO se puede (re)crear aquí: public/ queda de solo lectura para
# www-data a propósito (ver comentario de permisos en el Dockerfile), así que
# `storage:link` en runtime falla con "Permission denied" (confirmado con un
# `docker build` + `docker run` reales durante la validación de esta tarea).
# No tiene costo funcional: Nginx sirve /storage/* vía `alias` directo a
# storage/app/public, nunca a través de este symlink (ver
# docker/nginx/default.conf).

# Cachea config/rutas/vistas leyendo las variables de entorno REALES de este
# contenedor (inyectadas por docker-compose en tiempo de ejecución, nunca
# horneadas en la imagen) — por eso esto corre aquí y no en el Dockerfile: cachear
# en build time forzaría a tener el .env real presente durante `docker build`,
# exactamente lo que NO debe pasar (ver .dockerignore).
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
