#!/usr/bin/env sh
set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
  echo "APP_KEY no esta configurada. Configura APP_KEY en el entorno de despliegue." >&2
  exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Reemplaza el puerto definido por el entorno en la config de Nginx.
sed "s/__PORT__/${PORT:-10000}/g" /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

php artisan package:discover --ansi >/dev/null 2>&1 || true
php artisan config:cache --ansi
php artisan route:cache --ansi || true
php artisan view:cache --ansi
php artisan storage:link --ansi || true

php-fpm -D
exec nginx -g "daemon off;"
