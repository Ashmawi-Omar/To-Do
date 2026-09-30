FROM php:8.4-fpm-alpine

RUN apk add --no-cache git unzip su-exec

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
