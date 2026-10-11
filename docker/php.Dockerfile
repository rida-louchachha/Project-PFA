# PHP 8.3 + Composer image to run the Laravel backend / test suite without a local PHP 8.2+.
#   docker build -t travelplan-php:8.3 -f docker/php.Dockerfile docker
#   docker run --rm -v "$PWD:/app" -w /app travelplan-php:8.3 php artisan test
FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends unzip git libzip-dev \
 && docker-php-ext-install zip pdo_mysql \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
