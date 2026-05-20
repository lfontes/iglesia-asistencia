# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All PHP/Artisan/Composer commands must run inside the Sail container:

```bash
./vendor/bin/sail artisan <command>
./vendor/bin/sail composer <command>
./vendor/bin/sail npm <command>
```

### Common commands

```bash
# Start environment
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Run tests
composer test                    # clears config cache, then runs all tests
./vendor/bin/sail artisan test --filter=TestName   # single test

# Format code
./vendor/bin/sail composer exec pint

# Start full dev environment (server + queue + logs + Vite in parallel)
composer dev

# Build frontend assets
npm run build

# Initial project setup
composer setup
```

## Architecture

### Stack
- **Laravel 12** + **Filament 5** + **Livewire 4**, PHP 8.2+
- **MySQL** (Sail in dev, production Docker image with PHP 8.4 FPM + Nginx)
- Sessions, cache, and queues all use the **database** driver
- Frontend: Blade + Tailwind CSS 4 + Vite

### Domain model

The application manages church attendance and ministry. Core entities:

- **Persona** — member/person with normalized phone numbers and accent-aware search
- **Grupo** — small group with a `TipoGrupo`; people join via `ParticipacionGrupo` with a `RolGrupo`
- **Metagrupo** — umbrella over multiple Grupos
- **Evento** / **EventoFecha** — events with one or more dates; people register via `Asistencia` or `EventoInscripcion`
- **AsistenciaGrupo** — weekly attendance record per Grupo
- **WhatsAppMessage** / **WhatsAppBulkDispatch** — outbound messaging via Meta Graph API v23.0
- **IpnAula** / **IpnNino** / **IpnAsistencia** — children's program sub-module (Cluster `Ipn`)

### Filament admin panel (`/admin`)

All admin UI lives under `app/Filament/`:

- **Resources** — standard Filament CRUD resources (PersonaResource, GrupoResource, EventoResource, etc.)
- **Pages** — custom full-page views: `MisGrupos`, `MisMetagrupos`, `AsistenciasPendientes`, `ResumenAsistenciaGrupos`, `WhatsAppConversaciones`, `IpnTomarAsistencia`, etc.
- **Widgets** — dashboard widgets; `ResumenGeneralWidget` and `AsistenciasSemanalesGruposWidget` are registered globally in `AdminPanelProvider`
- **Clusters** — `Ipn` cluster groups all children's-program resources and pages

`AdminPanelProvider` controls role-based home URL routing: `facilitador` and `lider` users land on different pages than `admin`/`secretario`.

### Roles and permissions

Managed by **Spatie Laravel Permission**. Roles: `admin`, `secretario`, `facilitador`, `lider`, `coordinador_grupos`. Permission checks are done via helper methods on the `User` model (e.g. `canManageGrupos()`, `canAccessIpn()`).

Access to panel pages is further restricted by the `RestrictFacilitadorPanelAccess` middleware and per-page `canAccess()` overrides.

### Public routes

Outside the Filament panel (`routes/web.php`):

- `GET/POST /eventos/{eventoFecha}/inscripcion` — public event registration form (`EventoInscripcionController`)
- `GET/POST /webhooks/whatsapp` — Meta webhook verification and message ingestion (`WhatsAppWebhookController`)

### Key services

| Service | Responsibility |
|---|---|
| `WhatsAppService` | Meta Graph API v23.0 — send messages, templates, bulk campaigns |
| `PersonaMatchingService` | Duplicate detection and merging |
| `InvitationAudienceBuilder` | Segment-based audience targeting for WhatsApp campaigns |
| `AsistenciasPendientesService` | Computes groups with pending attendance records |
| `ImportarGruposCsvService` | Bulk group import from CSV |
| `AuditService` | Activity log helpers (backed by Spatie activitylog) |

Background jobs (`app/Jobs/`): `SendInvitationCampaignJob`, `SendEventoReminderBatchJob` — dispatched to the database queue.

### Custom CSS override

`public/css/filament/admin-overrides.css` is injected into the Filament panel head via a render hook in `AdminPanelProvider`. This file is built by Vite and committed for production deploys.
