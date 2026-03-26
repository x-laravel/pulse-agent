FROM php:8.4-cli-alpine

RUN apk add --no-cache unzip openssh-client \
    && docker-php-ext-install pdo pdo_mysql pcntl

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY src/composer.json src/composer.lock* ./

RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY src/ .

RUN mkdir -p bootstrap/cache \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
    && chmod -R 777 bootstrap/cache storage \
    && chmod +x artisan

CMD ["php", "artisan", "pulse:check"]
