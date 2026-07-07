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
