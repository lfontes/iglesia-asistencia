<?php

use App\Models\Persona;
use App\Models\Asistencia;
use App\Models\AsistenciaGrupo;
use App\Models\Evento;
use App\Models\EventoFecha;
use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
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

    $grupos = Grupo::query()
        ->where('activo', true)
        ->whereHas('tipoGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%crecimiento%']))
        ->orderBy('nombre')
        ->get();

    $inicioSemanaAnterior = $fechaRef->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
    $finSemanaAnterior = $fechaRef->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);
    $obtenerFacilitadores = function (Grupo $grupo) use ($fechaRef): array {
        return ParticipacionGrupo::query()
            ->where('grupo_id', $grupo->id)
            ->where(function ($query) use ($fechaRef): void {
                $query->whereNull('fecha_inicio')
                    ->orWhereDate('fecha_inicio', '<=', $fechaRef->toDateString());
            })
            ->where(function ($query) use ($fechaRef): void {
                $query->whereNull('fecha_fin')
                    ->orWhereDate('fecha_fin', '>=', $fechaRef->toDateString());
            })
            ->whereHas('rolGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) LIKE ?', ['%facilit%']))
            ->with('persona:id,nombre,apellido,telefono,telefono_normalizado')
            ->get()
            ->map(function (ParticipacionGrupo $participacion): array {
                $persona = $participacion->persona;
                $nombreCompleto = $persona ? trim(($persona->apellido ?? '').' '.($persona->nombre ?? '')) : 'Sin persona';

                return [
                    'persona_id' => $persona?->id,
                    'nombre' => $nombreCompleto,
                    'telefono' => $persona?->telefono,
                    'telefono_normalizado' => $persona?->telefono_normalizado,
                ];
            })
            ->values()
            ->all();
    };

    $pendientes = $grupos
        ->map(function (Grupo $grupo) use ($inicioSemanaAnterior, $finSemanaAnterior, $fechaRef, $obtenerFacilitadores): ?array {
            $frecuencia = $grupo->frecuencia_asistencia ?: Grupo::FRECUENCIA_SEMANAL;

            if ($frecuencia === Grupo::FRECUENCIA_MENSUAL) {
                $inicioMesAnterior = $fechaRef->copy()->subMonthNoOverflow()->startOfMonth();
                $finMesAnterior = $fechaRef->copy()->subMonthNoOverflow()->endOfMonth();

                $asistenciaMesAnterior = AsistenciaGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->whereBetween('fecha', [$inicioMesAnterior->toDateString(), $finMesAnterior->toDateString()])
                    ->exists();

                if ($asistenciaMesAnterior) {
                    return null;
                }

                $ultimaAsistencia = AsistenciaGrupo::query()
                    ->where('grupo_id', $grupo->id)
                    ->max('fecha');

                return [
                    'grupo_id' => $grupo->id,
                    'grupo' => $grupo->nombre,
                    'frecuencia' => $frecuencia,
                    'periodo_inicio' => $inicioMesAnterior->toDateString(),
                    'periodo_fin' => $finMesAnterior->toDateString(),
                    'ultima_asistencia' => $ultimaAsistencia,
                    'facilitadores' => $obtenerFacilitadores($grupo),
                ];
            }

            $asistenciaSemanaAnterior = AsistenciaGrupo::query()
                ->where('grupo_id', $grupo->id)
                ->whereBetween('fecha', [$inicioSemanaAnterior->toDateString(), $finSemanaAnterior->toDateString()])
                ->exists();

            if ($asistenciaSemanaAnterior) {
                return null;
            }

            $ultimaAsistencia = AsistenciaGrupo::query()
                ->where('grupo_id', $grupo->id)
                ->max('fecha');

            if ($frecuencia === Grupo::FRECUENCIA_QUINCENAL) {
                if (! $ultimaAsistencia) {
                    return null;
                }

                $inicioSemanaUltimaAsistencia = Carbon::parse($ultimaAsistencia)->startOfWeek(Carbon::MONDAY);
                $semanasDeDiferencia = $inicioSemanaUltimaAsistencia->diffInWeeks($inicioSemanaAnterior);

                if ($semanasDeDiferencia % 2 !== 0) {
                    return null;
                }
            }

            return [
                'grupo_id' => $grupo->id,
                'grupo' => $grupo->nombre,
                'frecuencia' => $frecuencia,
                'periodo_inicio' => $inicioSemanaAnterior->toDateString(),
                'periodo_fin' => $finSemanaAnterior->toDateString(),
                'ultima_asistencia' => $ultimaAsistencia,
                'facilitadores' => $obtenerFacilitadores($grupo),
            ];
        })
        ->filter()
        ->values();

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
