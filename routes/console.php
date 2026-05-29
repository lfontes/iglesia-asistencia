<?php

use App\Models\Persona;
use App\Models\Asistencia;
use App\Models\AsistenciaGrupo;
use App\Models\Evento;
use App\Models\EventoFecha;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\WhatsAppMessage;
use App\Services\AsistenciasPendientesService;
use App\Services\ImportarGruposCsvService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('personas:telefonos-duplicados', function () {
    $duplicados = DB::table('personas')
        ->selectRaw("NULLIF(regexp_replace(COALESCE(telefono, ''), '[^0-9]', '', 'g'), '') AS telefono_norm")
        ->selectRaw('COUNT(*) AS total')
        ->whereNotNull('telefono')
        ->groupBy('telefono_norm')
        ->havingRaw('COUNT(*) > 1')
        ->orderByDesc('total')
        ->get();

    if ($duplicados->isEmpty()) {
        $this->info('No se encontraron telefonos duplicados.');

        return self::SUCCESS;
    }

    $this->warn('Telefonos duplicados detectados:');
    foreach ($duplicados as $row) {
        $this->line("- {$row->telefono_norm}: {$row->total} registros");
    }

    return self::FAILURE;
})->purpose('Detecta telefonos duplicados normalizados en personas');

Artisan::command('personas:merge {keep_id : ID de la persona a conservar} {duplicate_ids* : IDs de las personas duplicadas a fusionar} {--dry-run : Solo muestra lo que haria, sin guardar}', function () {
    $keepId = (int) $this->argument('keep_id');
    $duplicateIds = collect((array) $this->argument('duplicate_ids'))
        ->map(fn ($id): int => (int) $id)
        ->filter(fn (int $id): bool => $id > 0 && $id !== $keepId)
        ->unique()
        ->values();
    $dryRun = (bool) $this->option('dry-run');

    if ($keepId <= 0 || $duplicateIds->isEmpty()) {
        $this->error('Debes indicar un ID a conservar y al menos un ID duplicado distinto.');

        return self::FAILURE;
    }

    $people = Persona::query()
        ->whereIn('id', collect([$keepId])->merge($duplicateIds)->all())
        ->orderBy('id')
        ->get()
        ->keyBy('id');

    if (! $people->has($keepId)) {
        $this->error("No existe la persona a conservar con ID {$keepId}.");

        return self::FAILURE;
    }

    $missing = $duplicateIds->filter(fn (int $id): bool => ! $people->has($id));

    if ($missing->isNotEmpty()) {
        $this->error('No existen estos IDs duplicados: '.$missing->implode(', '));

        return self::FAILURE;
    }

    $ids = collect([$keepId])->merge($duplicateIds)->all();

    $participaciones = ParticipacionGrupo::query()
        ->whereIn('persona_id', $ids)
        ->orderBy('grupo_id')
        ->orderBy('rol_grupo_id')
        ->orderBy('fecha_inicio')
        ->orderBy('id')
        ->get();

    $asistenciasGrupo = AsistenciaGrupo::query()
        ->whereIn('persona_id', $ids)
        ->orderBy('grupo_id')
        ->orderBy('fecha')
        ->orderBy('id')
        ->get();

    $asistenciasEvento = Asistencia::query()
        ->whereIn('persona_id', $ids)
        ->orderBy('evento_fecha_id')
        ->orderBy('id')
        ->get();

    $whatsAppMessages = WhatsAppMessage::query()
        ->whereIn('persona_id', $duplicateIds->all())
        ->get();

    $participacionesPlan = $participaciones
        ->groupBy(function (ParticipacionGrupo $participacion): string {
            return implode('|', [
                (string) $participacion->grupo_id,
                $participacion->rol_grupo_id === null ? 'null' : (string) $participacion->rol_grupo_id,
            ]);
        })
        ->map(function ($rows) use ($keepId) {
            $rows = $rows->values();
            $target = $rows->firstWhere('persona_id', $keepId) ?? $rows->sortBy([
                ['fecha_inicio', 'asc'],
                ['id', 'asc'],
            ])->first();

            $fechaInicio = $rows
                ->pluck('fecha_inicio')
                ->filter()
                ->map(fn ($value) => (string) $value)
                ->sort()
                ->first();

            $fechaFinRows = $rows->pluck('fecha_fin');
            $fechaFin = $fechaFinRows->contains(null)
                ? null
                : $fechaFinRows->filter()->map(fn ($value) => (string) $value)->sortDesc()->first();

            $observaciones = $rows
                ->pluck('observaciones')
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => trim((string) $value))
                ->unique()
                ->implode(' | ');

            $anio = $rows
                ->pluck('anio')
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value): int => (int) $value)
                ->sort()
                ->first();

            return [
                'target_id' => $target->id,
                'target_persona_id' => $target->persona_id,
                'group_id' => $target->grupo_id,
                'rol_grupo_id' => $target->rol_grupo_id,
                'target_will_change_persona' => (int) $target->persona_id !== $keepId,
                'ids_to_delete' => $rows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $target->id)->values()->all(),
                'merged' => [
                    'persona_id' => $keepId,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin' => $fechaFin,
                    'observaciones' => $observaciones !== '' ? $observaciones : null,
                    'anio' => $anio,
                    'recibe_recordatorios' => $rows->contains(fn (ParticipacionGrupo $row): bool => (bool) $row->recibe_recordatorios),
                ],
            ];
        })
        ->values();

    $asistenciasGrupoPlan = $asistenciasGrupo
        ->groupBy(fn (AsistenciaGrupo $row): string => $row->grupo_id.'|'.$row->fecha)
        ->map(function ($rows) use ($keepId) {
            $rows = $rows->values();
            $target = $rows->firstWhere('persona_id', $keepId) ?? $rows->sortBy('id')->first();

            return [
                'target_id' => $target->id,
                'target_persona_id' => $target->persona_id,
                'target_will_change_persona' => (int) $target->persona_id !== $keepId,
                'ids_to_delete' => $rows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $target->id)->values()->all(),
                'merged' => [
                    'persona_id' => $keepId,
                    'presente' => $rows->contains(fn (AsistenciaGrupo $row): bool => (bool) $row->presente),
                    'observaciones' => $rows->pluck('observaciones')->filter(fn ($value) => filled($value))->map(fn ($value) => trim((string) $value))->unique()->implode(' | ') ?: null,
                    'created_by' => $rows->pluck('created_by')->filter(fn ($value) => filled($value))->first(),
                ],
            ];
        })
        ->values();

    $asistenciasEventoPlan = $asistenciasEvento
        ->groupBy(fn (Asistencia $row): string => (string) $row->evento_fecha_id)
        ->map(function ($rows) use ($keepId) {
            $rows = $rows->values();
            $target = $rows->firstWhere('persona_id', $keepId) ?? $rows->sortBy('id')->first();

            return [
                'target_id' => $target->id,
                'target_persona_id' => $target->persona_id,
                'target_will_change_persona' => (int) $target->persona_id !== $keepId,
                'ids_to_delete' => $rows->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $target->id)->values()->all(),
                'merged' => [
                    'persona_id' => $keepId,
                    'presente' => $rows->contains(fn (Asistencia $row): bool => (bool) $row->presente),
                    'observaciones' => $rows->pluck('observaciones')->filter(fn ($value) => filled($value))->map(fn ($value) => trim((string) $value))->unique()->implode(' | ') ?: null,
                ],
            ];
        })
        ->values();

    $this->info('Persona a conservar:');
    $personaKeep = $people->get($keepId);
    $this->line("- {$personaKeep->id}: {$personaKeep->apellido} {$personaKeep->nombre} | Tel: ".($personaKeep->telefono ?: 'sin telefono').' | Nac: '.($personaKeep->fecha_nacimiento ?: 'sin fecha'));

    $this->warn('Duplicados a fusionar:');
    foreach ($duplicateIds as $duplicateId) {
        $persona = $people->get($duplicateId);
        $this->line("- {$persona->id}: {$persona->apellido} {$persona->nombre} | Tel: ".($persona->telefono ?: 'sin telefono').' | Nac: '.($persona->fecha_nacimiento ?: 'sin fecha'));
    }

    // Campos del perfil que se completan desde el duplicado si están vacíos en el registro a conservar
    $camposCompletar = ['fecha_nacimiento', 'telefono', 'email', 'departamento', 'responsable_persona_id', 'responsable_nombre', 'responsable_telefono', 'observaciones_ipn'];
    $completaciones = [];
    foreach ($camposCompletar as $campo) {
        if (blank($personaKeep->$campo)) {
            foreach ($duplicateIds as $dupId) {
                $valor = $people->get($dupId)->$campo;
                if (filled($valor)) {
                    $completaciones[$campo] = $valor;
                    break;
                }
            }
        }
    }

    $this->newLine();
    $this->line('Resumen del plan:');
    $this->line('- Participaciones de grupo a consolidar: '.$participacionesPlan->count());
    $this->line('- Asistencias de grupo a consolidar: '.$asistenciasGrupoPlan->count());
    $this->line('- Asistencias a eventos a consolidar: '.$asistenciasEventoPlan->count());
    $this->line('- Mensajes WhatsApp a reasignar: '.$whatsAppMessages->count());
    $this->line('- Personas a eliminar al final: '.$duplicateIds->count());
    if ($completaciones !== []) {
        $this->line('- Campos a completar en el registro conservado: '.implode(', ', array_keys($completaciones)));
    }

    if ($dryRun) {
        $this->warn('Dry run: no se realizaron cambios.');

        return self::SUCCESS;
    }

    DB::transaction(function () use (
        $keepId,
        $duplicateIds,
        $participacionesPlan,
        $asistenciasGrupoPlan,
        $asistenciasEventoPlan,
        $personaKeep,
        $completaciones
    ): void {
        if ($completaciones !== []) {
            $personaKeep->fill($completaciones)->save();
        }
        foreach ($participacionesPlan as $plan) {
            ParticipacionGrupo::query()
                ->whereKey($plan['target_id'])
                ->update($plan['merged']);

            if ($plan['ids_to_delete'] !== []) {
                ParticipacionGrupo::query()
                    ->whereIn('id', $plan['ids_to_delete'])
                    ->delete();
            }
        }

        foreach ($asistenciasGrupoPlan as $plan) {
            AsistenciaGrupo::query()
                ->whereKey($plan['target_id'])
                ->update($plan['merged']);

            if ($plan['ids_to_delete'] !== []) {
                AsistenciaGrupo::query()
                    ->whereIn('id', $plan['ids_to_delete'])
                    ->delete();
            }
        }

        foreach ($asistenciasEventoPlan as $plan) {
            Asistencia::query()
                ->whereKey($plan['target_id'])
                ->update($plan['merged']);

            if ($plan['ids_to_delete'] !== []) {
                Asistencia::query()
                    ->whereIn('id', $plan['ids_to_delete'])
                    ->delete();
            }
        }

        WhatsAppMessage::query()
            ->whereIn('persona_id', $duplicateIds->all())
            ->update(['persona_id' => $keepId]);

        Persona::query()
            ->whereIn('id', $duplicateIds->all())
            ->delete();
    });

    $this->info('Fusion completada correctamente.');

    return self::SUCCESS;
})->purpose('Fusiona personas duplicadas conservando un ID principal');

Artisan::command('grupos:asistencia-pendiente {--fecha= : Fecha de referencia YYYY-MM-DD} {--json : Salida en formato JSON}', function () {
    $fechaOption = $this->option('fecha');
    $asJson = (bool) $this->option('json');

    try {
        $fechaRef = filled($fechaOption)
            ? Carbon::parse((string) $fechaOption)->startOfDay()
            : now()->startOfDay();
    } catch (\Throwable) {
        $this->error('--fecha no tiene un formato valido. Usa YYYY-MM-DD.');

        return self::FAILURE;
    }

    $pendientes = app(AsistenciasPendientesService::class)->obtener($fechaRef);

    if ($asJson) {
        $this->line($pendientes->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    if ($pendientes->isEmpty()) {
        $this->info('No hay grupos de crecimiento con asistencia pendiente para el periodo actual.');

        return self::SUCCESS;
    }

    $this->warn('Grupos de crecimiento con asistencia pendiente:');

    foreach ($pendientes as $item) {
        $frecuencia = match ($item['frecuencia']) {
            Grupo::FRECUENCIA_MENSUAL => 'mensual',
            Grupo::FRECUENCIA_QUINCENAL => 'quincenal',
            default => 'semanal',
        };
        $this->line("- [{$frecuencia}] {$item['grupo']} (ID {$item['grupo_id']})");
        $this->line("  Periodo evaluado: {$item['periodo_inicio']} a {$item['periodo_fin']}");
        $ultimaAsistenciaTexto = $item['ultima_asistencia'] ?: 'sin asistencias registradas';
        $this->line("  Ultima asistencia: {$ultimaAsistenciaTexto}");

        if (empty($item['facilitadores'])) {
            $this->line('  Facilitadores: sin facilitadores activos en el grupo');

            continue;
        }

        $this->line('  Facilitadores:');
        foreach ($item['facilitadores'] as $facilitador) {
            $telefono = $facilitador['telefono'] ?: 'sin telefono';
            $this->line("    - {$facilitador['nombre']} ({$telefono})");
        }
    }

    return self::FAILURE;
})->purpose('Lista grupos de crecimiento activos sin asistencia en el periodo segun su frecuencia');

Artisan::command('personas:import {file : Ruta al archivo .xlsx} {--sheet=1 : Hoja a importar (base 1)} {--evento_id= : ID del evento para marcar asistencia} {--fecha= : Fecha del evento (YYYY-MM-DD) para marcar asistencia} {--dry-run : Solo valida y cuenta, no guarda}', function () {
    $file = $this->argument('file');
    $sheetNumber = (int) $this->option('sheet');
    $eventoId = $this->option('evento_id');
    $fechaOption = $this->option('fecha');
    $dryRun = (bool) $this->option('dry-run');

    if (! is_file($file)) {
        $this->error("No existe el archivo: {$file}");

        return self::FAILURE;
    }

    if (strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'xlsx') {
        $this->error('El archivo debe ser .xlsx');

        return self::FAILURE;
    }

    if ($sheetNumber < 1) {
        $this->error('--sheet debe ser un numero mayor o igual a 1.');

        return self::FAILURE;
    }

    $usarAsistencia = filled($eventoId) || filled($fechaOption);
    $eventoFecha = null;

    if ($usarAsistencia) {
        if (! filled($eventoId) || ! filled($fechaOption)) {
            $this->error('Debes enviar ambos: --evento_id y --fecha.');

            return self::FAILURE;
        }

        $evento = Evento::query()->find($eventoId);

        if (! $evento) {
            $this->error("No existe el evento con ID {$eventoId}.");

            return self::FAILURE;
        }

        try {
            $fechaEvento = Carbon::parse((string) $fechaOption)->toDateString();
        } catch (\Throwable) {
            $this->error('--fecha no tiene un formato valido. Usa YYYY-MM-DD.');

            return self::FAILURE;
        }

        $fechasCoincidentes = EventoFecha::query()
            ->where('evento_id', $evento->id)
            ->whereDate('fecha', $fechaEvento)
            ->get();

        if ($fechasCoincidentes->isEmpty()) {
            $this->error("No existe una fecha para el evento {$evento->id} en {$fechaEvento}.");

            return self::FAILURE;
        }

        if ($fechasCoincidentes->count() > 1) {
            $this->error("Hay varias fechas para evento {$evento->id} en {$fechaEvento}. Corrige la data o usa una fecha mas especifica.");

            return self::FAILURE;
        }

        $eventoFecha = $fechasCoincidentes->first();
    }

    $normalizeHeader = static function (?string $value): string {
        return (string) Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    };

    $resolveField = static function (string $header) use ($normalizeHeader): ?string {
        $normalized = $normalizeHeader($header);

        return match ($normalized) {
            'nombre', 'nombres', 'name' => 'nombre',
            'apellido', 'apellidos', 'last_name' => 'apellido',
            'fecha_nacimiento', 'fecha_de_nacimiento', 'nacimiento', 'birthdate' => 'fecha_nacimiento',
            'telefono', 'celular', 'movil', 'mobile', 'phone', 'telefono_celular' => 'telefono',
            default => null,
        };
    };

    $asDate = static function (mixed $value): ?string {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return Carbon::create(1899, 12, 30)
                ->addDays((int) floor((float) $value))
                ->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    };

    $reader = new XlsxReader();
    $reader->open($file);

    $sheetIndex = 0;
    $selectedSheetFound = false;
    $headerMap = [];
    $imported = 0;
    $duplicates = 0;
    $invalid = 0;
    $emptyRows = 0;
    $asistenciasMarcadas = 0;

    foreach ($reader->getSheetIterator() as $sheet) {
        $sheetIndex++;

        if ($sheetIndex !== $sheetNumber) {
            continue;
        }

        $selectedSheetFound = true;
        $rowIndex = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex++;
            $values = $row->toArray();

            $hasContent = collect($values)->contains(
                static fn (mixed $value): bool => $value !== null && trim((string) $value) !== ''
            );

            if (! $hasContent) {
                $emptyRows++;

                continue;
            }

            if ($rowIndex === 1) {
                foreach ($values as $index => $headerValue) {
                    $field = $resolveField((string) $headerValue);

                    if ($field !== null) {
                        $headerMap[$field] = $index;
                    }
                }

                if (! isset($headerMap['nombre'], $headerMap['apellido'])) {
                    $this->error('El encabezado debe incluir al menos las columnas nombre y apellido.');
                    $reader->close();

                    return self::FAILURE;
                }

                continue;
            }

            $nombre = trim((string) ($values[$headerMap['nombre']] ?? ''));
            $apellido = trim((string) ($values[$headerMap['apellido']] ?? ''));
            $telefono = isset($headerMap['telefono']) ? trim((string) ($values[$headerMap['telefono']] ?? '')) : '';
            $fechaNacimientoRaw = $headerMap['fecha_nacimiento'] ?? null;
            $fechaNacimiento = $fechaNacimientoRaw !== null ? $asDate($values[$fechaNacimientoRaw] ?? null) : null;

            if ($nombre === '' || $apellido === '') {
                $invalid++;
                $this->warn("Fila {$rowIndex}: nombre/apellido vacio. Se omite.");

                continue;
            }

            $data = [
                'nombre' => $nombre,
                'apellido' => $apellido,
                'fecha_nacimiento' => $fechaNacimiento,
                'telefono' => $telefono !== '' ? $telefono : null,
            ];

            if ($dryRun) {
                $exists = Persona::query()->where($data)->exists();

                if ($exists) {
                    $duplicates++;
                } else {
                    $imported++;
                }

                if ($eventoFecha) {
                    $personaExistente = Persona::query()->where($data)->first();

                    if (! $personaExistente) {
                        $asistenciasMarcadas++;
                    } else {
                        $asistenciaExiste = Asistencia::query()
                            ->where('persona_id', $personaExistente->id)
                            ->where('evento_fecha_id', $eventoFecha->id)
                            ->where('presente', true)
                            ->exists();

                        if (! $asistenciaExiste) {
                            $asistenciasMarcadas++;
                        }
                    }
                }

                continue;
            }

            $persona = Persona::firstOrCreate($data);

            if ($persona->wasRecentlyCreated) {
                $imported++;
            } else {
                $duplicates++;
            }

            if ($eventoFecha) {
                $yaEstabaPresente = Asistencia::query()
                    ->where('persona_id', $persona->id)
                    ->where('evento_fecha_id', $eventoFecha->id)
                    ->where('presente', true)
                    ->exists();

                $asistencia = Asistencia::updateOrCreate(
                    [
                        'persona_id' => $persona->id,
                        'evento_fecha_id' => $eventoFecha->id,
                    ],
                    [
                        'presente' => true,
                    ]
                );

                if (! $yaEstabaPresente && ($asistencia->wasRecentlyCreated || $asistencia->presente)) {
                    $asistenciasMarcadas++;
                }
            }
        }

        break;
    }

    $reader->close();

    if (! $selectedSheetFound) {
        $this->error("No existe la hoja #{$sheetNumber} en el archivo.");

        return self::FAILURE;
    }

    $mode = $dryRun ? 'SIMULACION (dry-run)' : 'IMPORTACION';
    $this->info("Resultado {$mode}:");
    $this->line("- Nuevos: {$imported}");
    $this->line("- Duplicados: {$duplicates}");
    $this->line("- Invalidos: {$invalid}");
    $this->line("- Filas vacias: {$emptyRows}");
    if ($eventoFecha) {
        $this->line("- Asistencias marcadas en evento {$eventoFecha->evento_id}, fecha {$eventoFecha->fecha}: {$asistenciasMarcadas}");
    }

    return self::SUCCESS;
})->purpose('Importa personas desde un archivo Excel (.xlsx)');

Artisan::command('grupos:importar-csv {path : Ruta absoluta o relativa al CSV} {--anio= : Anio de los grupos a crear o reutilizar} {--segmento=jovenes : Segmento etario a asignar a los grupos creados} {--dry-run : Simula la importacion sin guardar cambios}', function (ImportarGruposCsvService $service) {
    $path = (string) $this->argument('path');
    $anio = (int) ($this->option('anio') ?: now()->year);
    $segmento = (string) $this->option('segmento');
    $dryRun = (bool) $this->option('dry-run');

    $segmentosPermitidos = ['ninos', 'adolescentes', 'jovenes', 'adultos'];

    if (! in_array($segmento, $segmentosPermitidos, true)) {
        $this->error('El segmento debe ser uno de: '.implode(', ', $segmentosPermitidos));

        return self::FAILURE;
    }

    $absolutePath = str_starts_with($path, DIRECTORY_SEPARATOR)
        ? $path
        : base_path($path);

    try {
        $summary = $service->importar($absolutePath, $anio, $segmento, $dryRun);
    } catch (Throwable $exception) {
        report($exception);
        $this->error($exception->getMessage());

        return self::FAILURE;
    }

    if ($dryRun) {
        $this->warn('Ejecucion en modo dry-run. No se guardaron cambios.');
    }

    $this->newLine();
    $this->info('Importacion completada.');
    $this->line("Filas procesadas: {$summary['filas_procesadas']}");
    $this->line("Filas invalidas: {$summary['filas_invalidas']}");
    $this->line("Grupos creados: {$summary['grupos_creados']}");
    $this->line("Grupos existentes reutilizados: {$summary['grupos_existentes']}");
    $this->line("Personas nuevas: {$summary['personas_nuevas']}");
    $this->line("Personas existentes: {$summary['personas_existentes']}");
    $this->line("Coincidencias por telefono: {$summary['coincidencias_telefono']}");
    $this->line("Coincidencias por nombre: {$summary['coincidencias_nombre']}");
    $this->line("Coincidencias ambiguas: {$summary['ambiguas']}");
    $this->line("Conflictos por telefono: {$summary['conflictos_telefono']}");
    $this->line("Participaciones creadas: {$summary['participaciones_creadas']}");
    $this->line("Ya registradas: {$summary['ya_registradas']}");

    if (! empty($summary['detalles_conflictos_telefono'])) {
        $this->newLine();
        $this->warn('Detalle de conflictos por telefono:');

        foreach ($summary['detalles_conflictos_telefono'] as $index => $detalle) {
            $csv = $detalle['csv'];
            $this->line(($index + 1).". CSV: {$csv['apellido']}, {$csv['nombre']} | Tel: ".($csv['telefono'] ?: '-')." | FN: ".($csv['fecha_nacimiento'] ?: '-')." | Grupo: {$csv['grupo']}");

            foreach ($detalle['existentes'] as $existente) {
                $this->line("   BD: #{$existente['id']} {$existente['apellido']}, {$existente['nombre']} | Tel: ".($existente['telefono'] ?: '-')." | FN: ".($existente['fecha_nacimiento'] ?: '-'));
            }
        }
    }

    if (! empty($summary['detalles_ambiguas'])) {
        $this->newLine();
        $this->warn('Detalle de coincidencias ambiguas:');

        foreach ($summary['detalles_ambiguas'] as $index => $detalle) {
            $csv = $detalle['csv'];
            $this->line(($index + 1).". CSV: {$csv['apellido']}, {$csv['nombre']} | Tel: ".($csv['telefono'] ?: '-')." | FN: ".($csv['fecha_nacimiento'] ?: '-')." | Grupo: {$csv['grupo']}");

            foreach ($detalle['existentes'] as $existente) {
                $this->line("   BD: #{$existente['id']} {$existente['apellido']}, {$existente['nombre']} | Tel: ".($existente['telefono'] ?: '-')." | FN: ".($existente['fecha_nacimiento'] ?: '-'));
            }
        }
    }

    return self::SUCCESS;
})->purpose('Importa personas y crea grupos de crecimiento desde un CSV');
