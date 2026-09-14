FROM php:8.5-fpm

# Extensions are already included in the official PHP image
# Just install nginx and supervisor
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    && rm -rf /var/lib/apt/lists/*

# Copy code
COPY . /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy nginx config
COPY .kube/nginx/default.conf /etc/nginx/conf.d/default.conf

# Copy supervisor config
RUN echo -e "[supervisord]\nnodaemon=true\n\n[program:php-fpm]\ncommand=php-fpm\nautostart=true\nautorestart=true\n\n[program:nginx]\ncommand=nginx -g 'daemon off;'\nautostart=true\nautorestart=true" > /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
