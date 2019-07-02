FROM php:7.2-cli

RUN apt-get update && apt-get install git zip unzip -y
RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

WORKDIR /app

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install

COPY . .
CMD ["scripts/check_all.sh"]
