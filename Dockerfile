# Production-style image: the AOP cache and the OPcache file cache are built at image build time,
# the container runs with PREBUILT_CACHE on a read-only cache and serves its first request hot.
FROM php:8.5-cli

# OPcache is part of PHP 8.5 (before 8.5: RUN docker-php-ext-install opcache)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN rm -rf vendor/composer/autoload_* var \
 && php bin/generate.php --if-missing \
 && composer dump-autoload --no-dev --classmap-authoritative --no-interaction

# 1. The AOP cache: woven classes, proxies, advisor caches, metadata (fails the build if a file can not be woven)
ENV APP_MODE=prebuilt
RUN mkdir -p var/cache \
 && php vendor/bin/aspect cache:warmup:aop config/bootstrap.php --workers="$(nproc)"

# 2. The OPcache file cache: a new server process loads compiled scripts instead of compiling them
COPY docker/opcache.ini $PHP_INI_DIR/conf.d/zz-opcache.ini
RUN mkdir -p /var/cache/opcache \
 && php -d opcache.enable_cli=1 -d opcache.file_cache_only=1 -d opcache.file_cache_read_only=0 -d opcache.preload= \
        docker/opcache-prime.php /app /app/var/cache/prebuilt /app/public/index.php /app/config/bootstrap.php \
 && chown -R www-data /var/cache/opcache \
 && chown -R www-data:www-data /app/var/cache \
 && chmod -R a-w /app/var/cache

USER www-data
EXPOSE 8080
# The built-in server keeps the demo self-contained; in production run PHP-FPM with the same php.ini settings
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
