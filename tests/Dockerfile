FROM php:8.2-cli
RUN set -eux; apt-get update; apt-get install -y --no-install-recommends git unzip libonig-dev; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring; rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
