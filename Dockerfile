FROM php:8.1-cli

# Instala dependências mínimas do sistema (git/unzip/zip para o Composer)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    && docker-php-ext-install zip pdo_sqlite sockets \
    && rm -rf /var/lib/apt/lists/*

# Instala o Composer globalmente
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader

# Garante que o diretório de storage de uploads existe e é gravável
RUN mkdir -p storage/uploads

EXPOSE 8000

# Servidor embutido do PHP servindo a pasta public/ como document root
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
