# Contexto para IAs y agentes

Este repositorio es una aplicacion Laravel para gestion de asistencia, personas, grupos, eventos, permisos y panel administrativo de una iglesia.

## Stack principal

- PHP 8.3
- Laravel 12
- Filament 5
- MySQL para desarrollo local con Laravel Sail
- Base de datos de produccion: mysql
- Laravel Sail para entorno local

## Paquetes relevantes

- `filament/filament`
- `spatie/laravel-permission`
- `spatie/laravel-activitylog`
- `laravel/sail`
- `laravel/pail`
- `phpunit/phpunit`

## Entorno local

El entorno recomendado de desarrollo local es Laravel Sail con MySQL.

Comandos habituales:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
./vendor/bin/sail artisan test
./vendor/bin/sail composer install
./vendor/bin/sail npm run build
```

## Base de datos

En local se espera:

```env
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
```
