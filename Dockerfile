FROM php:8.1-cli

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install zip pdo_sqlite sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-ansi

COPY . .

RUN mkdir -p storage/uploads \
    && chown -R www-data:www-data storage || true \
    && chmod -R 0775 storage || true

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
