FROM composer:2 AS composer
FROM php:8.4-cli-bookworm

RUN apt-get update \
	&& apt-get install -y --no-install-recommends libonig-dev libsqlite3-dev unzip \
	&& docker-php-ext-install mbstring pdo_sqlite \
	&& rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress

COPY index.php .
COPY req ./req
COPY style ./style
COPY script ./script
COPY store/store.txt ./store/store.txt
COPY store/notice ./store/notice
COPY tests/Browser/config.php ./config.php
COPY tests/Browser/router.php ./router.php

RUN mkdir -p store \
	&& chmod a+rwX store \
	&& chown -R 1000:1000 /app

ENV XDEBUG_MODE=off
USER 1000:1000
CMD ["php", "-d", "display_errors=stderr", "-d", "date.timezone=UTC", "-S", "0.0.0.0:8080", "router.php"]
