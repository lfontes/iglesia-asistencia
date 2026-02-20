<?php

use App\Models\Persona;
use App\Models\Asistencia;
use App\Models\Evento;
use App\Models\EventoFecha;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
