FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.4-fpm-alpine
WORKDIR /var/www/html

RUN apk add --no-cache nginx gettext \
    && docker-php-ext-install pdo pdo_mysql bcmath exif pcntl \
    && rm -rf /var/cache/apk/*

COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build

COPY nginx.conf /etc/nginx/nginx.conf.template
COPY start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=10000

EXPOSE 10000

CMD ["/usr/local/bin/start.sh"]
