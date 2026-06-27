# syntax=docker/dockerfile:1.6
# Official Docker Hub images — works on GitHub Actions and most networks.
# Local build (Iran mirrors): see Dockerfile.iran or pass custom base image ARGs.

ARG PHP_IMAGE=php:8.2-cli-alpine
ARG COMPOSER_IMAGE=composer:2

# ─────────────────────────────────────────────────────────────────────────────
# Stage 0: Composer binary
# ─────────────────────────────────────────────────────────────────────────────
FROM ${COMPOSER_IMAGE} AS composer-bin

# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: PHP extensions (Swoole, Redis, PDO, GD, …)
# ─────────────────────────────────────────────────────────────────────────────
FROM ${PHP_IMAGE} AS ext-builder

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        linux-headers \
        postgresql-dev \
        libzip-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        openssl-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo pdo_pgsql pdo_mysql sockets pcntl zip opcache gd

RUN pecl install igbinary msgpack \
    && pecl install -o -f redis \
    && pecl install -o -f swoole \
    && docker-php-ext-enable igbinary msgpack redis swoole

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Runtime
# ─────────────────────────────────────────────────────────────────────────────
FROM ${PHP_IMAGE}

RUN apk add --no-cache \
        git unzip curl bash openssl tzdata \
        libpq libzip freetype libjpeg-turbo libpng \
        lz4-libs c-ares brotli-libs libstdc++

COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

RUN php -m | grep -E 'swoole|redis|igbinary|msgpack|pdo_pgsql' \
    && php -r "new Redis();" \
    && php -r "echo swoole_version();"

RUN { \
    echo "memory_limit=512M"; \
    echo "opcache.enable=1"; \
    echo "opcache.enable_cli=1"; \
    echo "opcache.validate_timestamps=0"; \
    echo "opcache.max_accelerated_files=100000"; \
    echo "opcache.memory_consumption=256"; \
    echo "date.timezone=Asia/Tehran"; \
    } > /usr/local/etc/php/conf.d/99-opcache.ini \
    && cp /usr/share/zoneinfo/Asia/Tehran /etc/localtime \
    && echo "Asia/Tehran" > /etc/timezone

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/logs storage/cache storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER app
EXPOSE 9501
CMD ["php", "server.php"]
