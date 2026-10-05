FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        zlib1g-dev \
    && docker-php-ext-install intl mysqli pdo_mysql zip \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/web/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY web/composer.json web/composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

COPY web/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html/writable

EXPOSE 80
