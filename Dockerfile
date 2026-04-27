FROM php:8.2-apache

RUN set -eux; \
    docker-php-ext-install pdo_mysql mbstring; \
    a2enmod rewrite headers

WORKDIR /var/www/html

COPY . /var/www/html

RUN mkdir -p /var/www/html/uploads/images /var/www/html/uploads/products /var/www/html/logs /var/www/html/temp \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/logs /var/www/html/temp

EXPOSE 80
