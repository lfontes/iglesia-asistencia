# Sistema de Auditoría - Iglesia Asistencia

## Resumen General

Se ha implementado un sistema completo de auditoría utilizando el paquete `spatie/laravel-activitylog`. Este sistema registra automáticamente todas las acciones (crear, actualizar, eliminar) en los modelos principales de la aplicación.

## Características Implementadas

### 1. **Instalación del Paquete**
- Versión: `spatie/laravel-activitylog ^4.0`
- Base de datos: Tabla `activity_log` con campos para:
  - `id`: Identificador único
  - `log_name`: Nombre del registro
  - `description`: Descripción de la acción
  - `subject_type`: Tipo de modelo auditado
  - `subject_id`: ID del modelo auditado
  - `event`: Evento (created, updated, deleted, restored)
  - `causer_type`: Tipo de usuario que realizó la acción
  - `causer_id`: ID del usuario
  - `properties`: Cambios en JSON
  - `batch_uuid`: ID de lote para agrupar operaciones
  - `created_at`: Timestamp de la acción

### 2. **Modelos Auditados**
Los siguientes modelos registran automáticamente sus cambios:
- **Persona**: Datos de personas en la iglesia
- **Grupo**: Grupos de trabajo/estudio
- **Evento**: Eventos organizados
- **EventoFecha**: Fechas de eventos
- **EventoInscripcion**: Inscripciones a eventos
- **User**: Usuarios del sistema
- **ParticipacionGrupo**: Participaciones en grupos
- **WhatsAppMessage**: Mensajes de WhatsApp
- **Asistencia**: Registros de asistencia a eventos
- **AsistenciaGrupo**: Registros de asistencia a grupos

### 3. **Observadores (Observers)**
Se crearon 10 observadores en `app/Observers/`:
- `PersonaObserver.php`
- `GrupoObserver.php`
- `EventoObserver.php`
- `EventoFechaObserver.php`
- `EventoInscripcionObserver.php`
- `UserObserver.php`
- `ParticipacionGrupoObserver.php`
- `WhatsAppMessageObserver.php`
- `AsistenciaObserver.php`
- `AsistenciaGrupoObserver.php`

Cada observer se encarga de capturar eventos de creación, actualización, eliminación y restauración.

### 4. **Servicio de Auditoría**
Se creó `app/Services/AuditService.php` con métodos útiles:

```php
// Registrar una acción manualmente
AuditService::log($modelo, 'created');
AuditService::log($modelo, 'updated', $cambios);

// Obtener registros de auditoría de un modelo
$logs = AuditService::getModelLogs($modelo);

// Obtener registros de un usuario
$logs = AuditService::getUserLogs();

// Obtener actividad reciente
$logs = AuditService::getRecentActivity();
```

### 5. **Panel Administrativo (Filament)**

#### Recurso ActivityLogResource
- Ubicación: `app/Filament/Resources/ActivityLogResource.php`
- Navegación: "Administración > Auditoría"
- Característica: Solo lectura (no se pueden editar ni eliminar registros)

**Columnas mostradas:**
- Acción (description)
- Tipo de Registro (subject_type)
- ID del Registro (subject_id)
- Usuario (causer.name)
- Evento (event) - con colores
- Fecha y Hora (created_at)

**Filtros disponibles:**
- Por evento (created, updated, deleted, restored)
- Por tipo de registro
- Por usuario
- Por rango de fechas

#### Widget RecentActivityWidget
- Ubicación: `app/Filament/Widgets/RecentActivityWidget.php`
- Se muestra en el dashboard (visible solo para admin y director_ipn)
- Muestra los últimos 10 registros de actividad

## Uso en Código

### Acceder a Registros de Auditoría

```php
use Spatie\Activitylog\Models\Activity;

// Obtener todos los registros
$activities = Activity::all();

// Obtener registros de un modelo específico
$persona = Persona::find(1);
$activities = $persona->activities;

// Obtener registros de un usuario
$user = User::find(1);
$activities = Activity::causedBy($user)->get();

// Filtrar por evento
$created = Activity::where('event', 'created')->get();
$updated = Activity::where('event', 'updated')->get();

// Filtrar por rango de fechas
$thisWeek = Activity::whereBetween('created_at', [
    now()->startOfWeek(),
    now()->endOfWeek()
])->get();

// Obtener los cambios específicos
$activity = Activity::first();
$changes = $activity->properties; // JSON con 'old' y 'new' para updates
```

### Ejemplos Prácticos

#### Generar Reportes
```php
// Auditoría de una persona en particular
$persona = Persona::find(1);
foreach ($persona->activities as $activity) {
    echo "{$activity->event} por {$activity->causer->name} en {$activity->created_at}";
}

// Actividad de un usuario
$user = User::find(1);
$actions = Activity::causedBy($user)->count();
echo "El usuario realizó {$actions} acciones";
```

#### Mostrar Cambios
```php
$activity = Activity::where('event', 'updated')->first();

if ($activity->event === 'updated') {
    $old = $activity->properties['old'];
    $new = $activity->properties['new'];
    
    echo "Cambios realizados: ";
    foreach ($new as $field => $value) {
        echo "{$field}: {$old[$field]} → {$value}";
    }
}
```

## Configuración

### Archivo: `config/activitylog.php`

```php
return [
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true), // Habilitar/deshabilitar
    'delete_records_older_than_days' => 365, // Limpiar registros antiguos
    'default_log_name' => 'default', // Nombre por defecto
    'default_auth_driver' => null, // Driver de autenticación
    'subject_returns_soft_deleted_models' => false,
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];
```

### Variables de Entorno (.env)

```
ACTIVITY_LOGGER_ENABLED=true
ACTIVITY_LOGGER_TABLE_NAME=activity_log
```

## Limpieza de Datos

Para limpiar registros antiguos (más de 365 días):

```bash
./vendor/bin/sail artisan activity:clean
```

Para personalizar según necesidad:
```bash
./vendor/bin/sail artisan activity:clean --days=90
```

## Ventajas de Esta Implementación

✅ **Automática**: No requiere código manual en cada acción  
✅ **Completa**: Registra creación, actualización, eliminación y restauración  
✅ **Segura**: Usuario autenticado asociado a cada acción  
✅ **Eficiente**: Usa Observers de Laravel  
✅ **Rastreable**: Guarda cambios anteriores y nuevos  
✅ **Visual**: Panel de Filament para ver auditoría  
✅ **Flexible**: Fácil de extender a más modelos  
✅ **Consultable**: API robusta para queries  

## Extensión a Nuevos Modelos

Para agregar auditoría a un nuevo modelo:

1. Crear un Observer en `app/Observers/MiModeloObserver.php`
2. Registrarlo en `AppServiceProvider.php`
3. ¡Listo! El modelo quedará auditado automáticamente

```php
// 1. Crear Observer
namespace App\Observers;
use App\Models\MiModelo;

class MiModeloObserver {
    public function created(MiModelo $modelo) {
        AuditService::log($modelo, 'created');
    }
    // ... más métodos
}

// 2. Registrar en AppServiceProvider
MiModelo::observe(MiModeloObserver::class);
```

## Seguridad y Privacidad

- Solo usuarios autenticados pueden ver auditoría (en Filament, solo admin y director_ipn)
- Los registros son de solo lectura para prevenir manipulación
- Se registra el usuario exacto que realizó cada acción
- Los cambios se guardan en JSON encriptado en la base de datos

## Próximos Pasos Recomendados

1. ✅ Implementado: Sistema básico de auditoría
2. **TODO**: Crear reportes de auditoría en PDF
3. **TODO**: Agregar filtros más avanzados en el panel
4. **TODO**: Implementar notificaciones de acciones críticas
5. **TODO**: Crear backups automáticos de registros de auditoría
6. **TODO**: Agregar búsqueda fulltext en logs

---

**Fecha de Implementación**: 22 de Abril de 2026  
**Paquete**: `spatie/laravel-activitylog v4.12.3`  
**Rama**: `feature/implement-activity-logging`
