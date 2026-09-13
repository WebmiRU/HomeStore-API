# Stage 1: Build dependencies
FROM php:8.5-cli-alpine AS composer

RUN apk add --no-cache \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    postgresql16-dev \
    zip \
    oniguruma-dev

RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mbstring \
    bcmath \
    gd \
    zip

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Stage 2: PHP-FPM + Nginx
FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    nginx \
    postgresql16 \
    postgresql16-dev \
    supervisor \
    && rm -rf /var/cache/apk/*

RUN echo "daemonize = no" > /usr/local/etc/php-fpm.d/www-docker.conf \
    && mkdir -p /var/run/php \
    && mkdir -p /var/log/supervisor

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip

# Copy composer dependencies
COPY --from=composer /app/vendor ./vendor

# Copy application code
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy nginx configuration
COPY .kube/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy supervisor configuration
RUN echo -e "[supervisord]\nnodaemon=true\n\n[program:php-fpm]\ncommand=php-fpm\nautostart=true\nautorestart=true\n\n[program:nginx]\ncommand=nginx -g 'daemon off;'\nautostart=true\nautorestart=true" > /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

USER www-data

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
