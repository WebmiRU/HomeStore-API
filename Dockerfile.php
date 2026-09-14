FROM php:8.5-fpm

RUN echo "deb [trusted=yes] https://deb.debian.org/debian trixie main" > /etc/apt/sources.list && \
    apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm", "-F"]
