# syntax=docker.arvancloud.ir/docker/dockerfile:1.6
# BuildKit required: DOCKER_BUILDKIT=1 docker build .

# ─────────────────────────────────────────────────────────────────────────────
# Stage 0: Composer binary
# ─────────────────────────────────────────────────────────────────────────────
FROM docker.arvancloud.ir/composer:latest AS composer-bin

# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: Extension builder
# ─────────────────────────────────────────────────────────────────────────────
FROM docker.arvancloud.ir/php:8.2-cli-alpine3.18 AS ext-builder

RUN printf 'https://linux-mirror.liara.ir/repository/alpine/v3.18/main\nhttps://linux-mirror.liara.ir/repository/alpine/v3.18/community\n' \
    > /etc/apk/repositories

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
        autoconf g++ make linux-headers \
        postgresql-dev libzip-dev freetype-dev libjpeg-turbo-dev libpng-dev openssl-dev

# نصب redis به همراه dependency هاش: igbinary و msgpack
RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
        php82-pecl-swoole \
        php82-pecl-redis \
        php82-pecl-igbinary \
        php82-pecl-msgpack

RUN docker-php-ext-install -j$(nproc) pdo pdo_pgsql pdo_mysql sockets pcntl zip opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# کپی همه .so ها شامل igbinary و msgpack که redis بهشون نیاز داره
RUN PHP_EXT_DIR="$(php -r 'echo ini_get("extension_dir");')" \
    && cp /usr/lib/php82/modules/swoole.so   "$PHP_EXT_DIR/swoole.so" \
    && cp /usr/lib/php82/modules/redis.so    "$PHP_EXT_DIR/redis.so" \
    && cp /usr/lib/php82/modules/igbinary.so "$PHP_EXT_DIR/igbinary.so" \
    && cp /usr/lib/php82/modules/msgpack.so  "$PHP_EXT_DIR/msgpack.so"

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: Runtime
# ─────────────────────────────────────────────────────────────────────────────
FROM docker.arvancloud.ir/php:8.2-cli-alpine3.18

RUN printf 'https://linux-mirror.liara.ir/repository/alpine/v3.18/main\nhttps://linux-mirror.liara.ir/repository/alpine/v3.18/community\n' \
    > /etc/apk/repositories

RUN --mount=type=cache,target=/var/cache/apk,sharing=locked \
    apk add --no-cache \
        git unzip curl bash openssl tzdata mariadb-client \
        libpq libzip freetype libjpeg-turbo libpng \
        lz4-libs c-ares brotli-libs libstdc++

COPY --from=ext-builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=ext-builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# ترتیب مهمه: اول igbinary و msgpack، بعد redis که بهشون وابسته‌ست
RUN docker-php-ext-enable igbinary msgpack swoole redis \
    && php -m | grep -E 'swoole|redis|igbinary|msgpack' \
    && php -r "new Redis();" \
    && echo "✅ Redis class OK" \
    && php -r "echo swoole_version();" \
    && echo "✅ Swoole OK"

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
RUN composer config -g repos.packagist composer https://package-mirror.liara.ir/repository/composer/

RUN addgroup -g 1000 app && adduser -D -u 1000 -G app app

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/root/.composer/cache \
    composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts

COPY . .
# نکته: از --classmap-authoritative استفاده نمی‌کنیم. آن flag، fallback مبتنی بر
# PSR-4 را کامل غیرفعال می‌کند؛ پس هر کلاسی که (به‌خاطر کش لایه یا دیپلوی ناقص)
# در classmap نباشد، با «class doesn't exist» شکست می‌خورد و کل DI/route را می‌خواباند.
# با --optimize تنها، classmap برای سرعت ساخته می‌شود ولی PSR-4 به‌عنوان شبکهٔ ایمنی می‌ماند.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R app:app storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER app
EXPOSE 9501
CMD ["php", "server.php"]