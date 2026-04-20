# Iglesia Asistencia

Aplicación web desarrollada en **Laravel** para la gestión ministerial y administrativa de una iglesia.

El proyecto combina un **panel administrativo con Filament**, gestión de **personas**, **grupos**, **metagrupos**, **eventos**, **asistencias** e **inscripciones públicas**, además de una capa de mensajería y automatización para **WhatsApp**.

## Objetivo

Centralizar en una sola plataforma tareas habituales de la vida de la iglesia, por ejemplo:

- administración de personas y datos de contacto
- organización de grupos y roles ministeriales
- gestión de eventos y fechas específicas
- registro de asistencias
- inscripciones públicas a eventos
- seguimiento y campañas de recordatorios por WhatsApp
- acceso diferenciado según rol de usuario

## Características principales

- **Panel administrativo en Filament** con recursos, páginas y widgets personalizados.
- **Gestión de personas** con datos básicos, teléfono normalizado, email y otros atributos.
- **Gestión de grupos** y **metagrupos**.
- **Roles ministeriales** dentro de grupos.
- **Eventos** con una o varias fechas.
- **Asistencias** para eventos y grupos.
- **Inscripción pública** a eventos desde rutas web.
- **Usuarios y permisos** con Spatie Laravel Permission.
- **Integración con WhatsApp** para webhooks, mensajes y envíos masivos.
- **Colas en base de datos** para procesos diferidos.

## Stack tecnológico

### Backend

- PHP 8.2+
- Laravel 12
- Filament 3
- Spatie Laravel Permission

### Frontend

- Blade
- Vite
- Tailwind CSS 4

### Base de datos

- **Desarrollo local:** MySQL (vía Laravel Sail)
- **Producción / Render:** PostgreSQL

### Infraestructura

- Docker / Dockerfile multi-stage
- Laravel Sail para entorno local
- Despliegue en Render

## Módulos del sistema

El proyecto incluye, entre otros, estos dominios funcionales:

- Personas
- Grupos
- Tipos de grupo
- Roles de grupo
- Participación en grupos
- Metagrupos
- Eventos
- Tipos de evento
- Fechas de evento
- Asistencias
- Inscripciones a eventos
- Mensajes de WhatsApp
- Despachos masivos de WhatsApp
- Usuarios y permisos

## Accesos y roles

El sistema contempla acceso al panel para roles como:

- `admin`
- `secretario`
- `facilitador`
- `lider`

Además, el panel tiene comportamiento diferenciado según el rol del usuario, con páginas y accesos específicos para facilitadores y líderes.

## Rutas públicas relevantes

Además del panel administrativo, el sistema expone rutas públicas para:

- **Webhook de WhatsApp**
  - `GET /webhooks/whatsapp`
  - `POST /webhooks/whatsapp`
- **Inscripción pública a eventos**
  - `GET /eventos/{eventoFecha}/inscripcion`
  - `POST /eventos/{eventoFecha}/inscripcion`

## Requisitos para desarrollo local

- Docker Desktop o Docker Engine
- Docker Compose
- Composer
- Node.js 20+
- NPM

> Aunque el proyecto incluye Laravel Sail, también puedes correrlo sin Sail si prefieres montar tu propio entorno PHP + MySQL.

## Instalación local

### Opción 1: usando Sail

```bash
git clone https://github.com/lfontes/iglesia-asistencia.git
cd iglesia-asistencia
composer install
cp .env.example .env
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

### Opción 2: usando el script de Composer

El proyecto define un script de ayuda que automatiza la puesta en marcha básica:

```bash
composer setup
```

Ese script instala dependencias, crea `.env` si no existe, genera la clave de aplicación, corre migraciones e instala/compila assets.

## Variables de entorno importantes

El archivo `.env.example` ya trae una base de configuración. Revisa especialmente:

```env
APP_NAME="Iglesia de los Libres"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=587
MAIL_USERNAME=noreply@iglesiadeloslibres.com.ar
MAIL_PASSWORD=CHANGE_ME_SMTP_PASSWORD
MAIL_FROM_ADDRESS="noreply@iglesiadeloslibres.com.ar"
MAIL_FROM_NAME="${APP_NAME}"
```

Si vas a usar la integración con WhatsApp, agrega también las variables necesarias de tu proveedor o de Meta según tu implementación actual.

## Comandos útiles

### Levantar el entorno local

```bash
./vendor/bin/sail up -d
```

### Ejecutar migraciones

```bash
./vendor/bin/sail artisan migrate
```

### Ejecutar assets en desarrollo

```bash
./vendor/bin/sail npm run dev
```

### Compilar assets para producción

```bash
npm run build
```

### Ejecutar pruebas

```bash
php artisan test
```

### Ejecutar worker de colas

```bash
php artisan queue:work
```

## Scripts de Composer disponibles

El proyecto define scripts útiles en `composer.json`:

```bash
composer setup
composer dev
composer test
```

- `composer setup`: instalación inicial
- `composer dev`: arranca servidor, cola, logs y Vite en paralelo
- `composer test`: limpia config y ejecuta tests

## Panel administrativo

El panel administrativo está configurado con Filament en la ruta:

```text
/admin
```

Incluye:

- recursos CRUD
- dashboard
- widgets de resumen
- páginas específicas para grupos y metagrupos
- flujo de acceso diferenciado según rol

## Estructura general del proyecto

```text
app/
  Filament/
    Pages/
    Resources/
    Widgets/
  Http/
    Controllers/
  Jobs/
  Models/
  Providers/
  Services/

database/
  migrations/

resources/
  views/

routes/
  web.php
```

## Despliegue en Render

El repositorio incluye:

- `Dockerfile`
- `render.yaml`
- `start.sh`
- `nginx.conf`

La configuración actual de Render usa:

- servicio web Docker
- `healthCheckPath: /up`
- `DB_CONNECTION=pgsql`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- `preDeployCommand: php artisan migrate --force`

## Producción

El `Dockerfile` compila dependencias PHP y frontend por separado, y luego arma una imagen final con:

- PHP 8.4 FPM Alpine
- Nginx
- extensión `pdo_pgsql`
- build de Vite

El script `start.sh` además:

- valida que `APP_KEY` exista
- genera configuración de Nginx con el puerto dinámico de Render
- cachea configuración, rutas y vistas
- intenta crear el symlink de storage

## Notas sobre colas y procesos en segundo plano

El proyecto utiliza tablas y jobs para procesos diferidos, por ejemplo campañas y recordatorios de WhatsApp.

Si en producción vas a depender de esos procesos, conviene contar con un **worker de colas** dedicado además del servicio web.

## Estado actual de pruebas

Actualmente el proyecto incluye la estructura estándar de pruebas de Laravel, por lo que es recomendable ampliar la cobertura para:

- inscripción pública a eventos
- permisos por rol
- matching de personas
- envío de recordatorios y campañas
- webhooks de WhatsApp

## Posibles mejoras futuras

- mayor cobertura de tests
- documentación funcional para usuarios de secretaría y líderes
- endurecimiento anti-spam en formularios públicos
- worker dedicado para colas en producción
- documentación de variables específicas de WhatsApp

## Licencia

Este proyecto se distribuye bajo la licencia MIT, de acuerdo con la configuración actual del repositorio.

---

Si este repositorio va a usarse públicamente, una buena siguiente mejora sería agregar:

- capturas de pantalla del panel
- diagrama simple del modelo de datos
- guía de despliegue paso a paso
- guía funcional para administradores de la iglesia
