# Imagem de produção standalone do Iwori Agenda.
# Para desenvolvimento local, use o Herd ou o Sail (compose.yaml).

# ── Estágio 1: build dos assets front-end ────────────────────────────
FROM node:24-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-fund --no-audit
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Estágio 2: dependências PHP de produção ──────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-dev --ignore-platform-reqs

# ── Estágio final: PHP 8.4 + Apache ──────────────────────────────────
FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql opcache \
    && a2enmod rewrite headers

# PHP com a configuração de produção (sem display_errors, sem args nos traces) e
# sem o header X-Powered-By; Apache sem versão/SO no header Server e nas páginas
# de erro. O zz- garante que estes arquivos carreguem depois dos padrões.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\n' > "$PHP_INI_DIR/conf.d/zz-security.ini" \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-security.conf \
    && a2enconf zz-security

# Aponta o docroot do Apache para o public/ do Laravel.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html

COPY --from=vendor /app ./
COPY --from=assets /app/public/build ./public/build

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
