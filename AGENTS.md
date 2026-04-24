# Contexto para IAs y agentes

Este repositorio es una aplicacion Laravel para gestion de asistencia, personas, grupos, eventos, permisos y panel administrativo de una iglesia.

## Stack principal

- PHP 8.3
- Laravel 12
- Filament 3.3
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
```

Si se ejecuta fuera de Sail, verificar que la version local de PHP y extensiones coincidan con las dependencias instaladas.

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

No asumir el motor de base de datos de produccion desde este archivo. Verificar la configuracion real del deploy antes de escribir migraciones, queries especificas del motor o cambios de infraestructura.

## Convenciones del proyecto

- El panel administrativo esta construido con Filament en `app/Filament`.
- La version instalada de Filament es 3.x; al momento de escribir este archivo, `composer show filament/filament` reporta `v3.3.48`.
- Vite existe en `package.json` y `vite.config.js`, pero el frontend principal del sistema es Blade/Filament. No asumir una SPA.
- Los modelos viven en `app/Models`.
- Las migraciones viven en `database/migrations`.
- Los seeders viven en `database/seeders`.
- Las rutas web viven en `routes/web.php`.
- Las rutas publicas incluyen webhooks de WhatsApp e inscripciones a eventos.
- Los permisos y roles usan Spatie Laravel Permission.
- Las colas, sesiones y cache pueden usar base de datos segun `.env`.

## Roles principales

El panel contempla roles como:

- `admin`
- `secretario`
- `facilitador`
- `lider`
- `coordinador_grupos`
- `director_ipn`
- `servidor_ipn`

Antes de cambiar accesos en Filament, revisar los metodos de permisos existentes en `App\Models\User`.

## Recomendaciones para agentes

- Preferir cambios chicos y consistentes con los patrones actuales del proyecto.
- No asumir SQLite: el desarrollo local usa MySQL via Sail.
- Antes de escribir SQL especifico del motor, verificar si el entorno objetivo usa MySQL u otro motor.
- Usar comandos Sail cuando el contexto sea desarrollo local.
- No publicar archivos de vendor, stubs o vistas de paquetes salvo que sea intencional.
- Evitar commitear `.env`, caches, resultados de pruebas o archivos generados innecesarios.
- Si se modifica el panel, revisar tanto recursos como paginas, widgets y restricciones por rol.
- Si se modifica auditoria o permisos, agregar o ajustar pruebas de feature.
- Usar componentes nativos de Filament siempre que sea posible.

## Comandos utiles

```bash
composer install
composer test
npm install
npm run build
```

Con Sail:

```bash
./vendor/bin/sail composer install
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan test
./vendor/bin/sail npm run build
```
