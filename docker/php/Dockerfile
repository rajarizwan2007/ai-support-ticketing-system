FROM php:8.4-fpm

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl libpq-dev libzip-dev libicu-dev libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip intl bcmath mbstring opcache pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Run PHP-FPM as a user matching the host UID/GID so bind-mounted files stay writable
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g www-data www-data \
    && mkdir -p /var/www/.config /var/www/.composer && chown -R www-data:www-data /var/www/.config /var/www/.composer

WORKDIR /var/www/html
USER www-data

EXPOSE 9000
CMD ["php-fpm"]
