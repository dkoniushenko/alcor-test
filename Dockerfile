FROM php:8.4-cli

RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Tools Composer needs to fetch (git) and extract (unzip) packages
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

# Composer, pinned to major version 2, copied from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

WORKDIR /app

CMD ["php", "-a"]
