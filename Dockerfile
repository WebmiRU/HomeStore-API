FROM php:8.5-fpm

WORKDIR /var/www/html

# Debian mirrors (override with --build-arg, e.g. for CI outside RU)
ARG DEBIAN_MIRROR=https://mirror.yandex.ru/debian
ARG DEBIAN_SECURITY_MIRROR=https://mirror.yandex.ru/debian-security

# php extensions + build tools
RUN rm -f /etc/apt/sources.list.d/*.sources \
    && printf 'deb [trusted=yes] %s trixie main\ndeb [trusted=yes] %s trixie-updates main\ndeb [trusted=yes] %s trixie-security main\n' \
        "$DEBIAN_MIRROR" "$DEBIAN_MIRROR" "$DEBIAN_SECURITY_MIRROR" > /etc/apt/sources.list \
    && apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libpq-dev \
        libzip-dev \
        libonig-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && export CFLAGS="-O0 -g0" CXXFLAGS="-O0 -g0" \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j1 \
        pdo_pgsql \
        pgsql \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        gd \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Cache layer: composer dependencies are reinstalled only when
# composer.json/composer.lock change, not on every code change
COPY composer.json composer.lock ./
RUN COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader --no-scripts

# Application code
COPY . /var/www/html

# Generated PDF fonts (tc-lib-pdf-font) are committed in resources/pdf/fonts
# and copied into the vendor tree — no font regeneration at build time
COPY resources/pdf/fonts /var/www/html/vendor/tecnickcom/tc-lib-pdf-font/target/fonts

# Final install runs post-autoload scripts (package:discover etc.),
# cheap because packages already exist in layers above
RUN COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader \
    && mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && php artisan config:clear 2>/dev/null || true \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -rf /tmp/composer-cache resources/pdf/fonts

EXPOSE 9000

CMD ["php-fpm", "-F"]