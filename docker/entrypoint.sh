#!/bin/sh
set -e

# Porta definida pela plataforma (o Render usa PORT=10000); sem PORT, segue na 80.
if [ -n "$PORT" ] && [ "$PORT" != "80" ]; then
    sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

# CA do MySQL gerenciado (ex.: Aiven). Secret files do Render só são legíveis
# pelo root, então copiamos para um caminho que o Apache (www-data) lê.
if [ -n "$MYSQL_ATTR_SSL_CA" ]; then
    if [ ! -f "$MYSQL_ATTR_SSL_CA" ]; then
        echo "Certificado CA do MySQL não encontrado em $MYSQL_ATTR_SSL_CA" >&2
        exit 1
    fi
    install -m 644 "$MYSQL_ATTR_SSL_CA" /usr/local/etc/mysql-ca.pem
    export MYSQL_ATTR_SSL_CA=/usr/local/etc/mysql-ca.pem
fi

# Roda as migrations apenas quando habilitado explicitamente (serviço app).
if [ "$RUN_MIGRATIONS" = "1" ]; then
    php artisan migrate --force
fi

# Ambiente de demonstração: recria a conta demo a cada início do contêiner
# (no Render Free isso inclui cada vez que o serviço acorda), além do reset
# diário agendado. Nunca defina DEMO_MODE no serviço de produção.
if [ "$DEMO_MODE" = "true" ] || [ "$DEMO_MODE" = "1" ]; then
    php artisan demo:reset
fi

# Fotos de perfil ficam no disco "public"; o link public/storage não vem do git.
[ -e public/storage ] || php artisan storage:link

# Cacheia configuração, rotas e views para produção.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Fila e agendador no mesmo contêiner, para planos sem worker/cron dedicado
# (ex.: Render Free). No compose.prod.yaml eles têm serviços próprios.
if [ "$RUN_WORKERS" = "1" ]; then
    (while true; do runuser -u www-data -- php artisan queue:work --tries=3 --timeout=90 --max-time=3600; sleep 5; done) &
    (while true; do runuser -u www-data -- php artisan schedule:work; sleep 5; done) &
fi

exec "$@"
