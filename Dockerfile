FROM php:8.4-cli-alpine

RUN apk add --no-cache $PHPIZE_DEPS git unzip libpq-dev linux-headers \
    && docker-php-ext-install pdo_pgsql pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

