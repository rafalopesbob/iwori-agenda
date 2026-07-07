#!/bin/sh
set -e

# Roda as migrations apenas quando habilitado explicitamente (serviço app).
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php artisan migrate --force
fi

# Cacheia configuração, rotas e views para produção.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
