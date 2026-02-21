<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use App\Filament\Resources\EventoFechaResource;
use App\Models\Asistencia;
use App\Models\Persona;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

class EditEventoFecha extends EditRecord
{
    protected static string $resource = EventoFechaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar_presentes')
                ->label('Importar presentes (Excel)')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('archivo_excel')
                        ->label('Archivo Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->directory('imports/asistencia')
                        ->disk('local')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relativePath = $data['archivo_excel'] ?? null;

                    if (! is_string($relativePath) || $relativePath === '') {
                        Notification::make()
                            ->title('No se recibió el archivo')
                            ->danger()
                            ->send();

                        return;
                    }

                    $absolutePath = Storage::disk('local')->path($relativePath);

                    if (! is_file($absolutePath)) {
                        Notification::make()
                            ->title('No se encontró el archivo subido')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $summary = $this->importarPresentesDesdeExcel($absolutePath);
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('No se pudo importar el archivo')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Importación completada')
                        ->body(implode("\n", [
                            "Filas procesadas: {$summary['procesadas']}",
                            "Personas nuevas: {$summary['personas_nuevas']}",
                            "Personas existentes: {$summary['personas_existentes']}",
                            "Asistencias marcadas: {$summary['asistencias_marcadas']}",
                            "Ya estaban presentes: {$summary['ya_presentes']}",
                            "Filas inválidas: {$summary['invalidas']}",
                            "Coincidencias ambiguas: {$summary['ambiguas']}",
                        ]))
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @return array{
     *     procesadas:int,
     *     personas_nuevas:int,
     *     personas_existentes:int,
     *     asistencias_marcadas:int,
     *     ya_presentes:int,
     *     invalidas:int,
     *     ambiguas:int
     * }
     */
    protected function importarPresentesDesdeExcel(string $absolutePath): array
    {
        $personas = Persona::query()
            ->select(['id', 'nombre', 'apellido', 'telefono', 'fecha_nacimiento'])
            ->get();

        $personasPorClave = $this->indexarPersonasPorNombreApellido($personas);
        $headerMap = [];
        $sheetFound = false;
        $summary = [
            'procesadas' => 0,
            'personas_nuevas' => 0,
            'personas_existentes' => 0,
            'asistencias_marcadas' => 0,
            'ya_presentes' => 0,
            'invalidas' => 0,
            'ambiguas' => 0,
        ];

        $reader = new XlsxReader();
        $reader->open($absolutePath);

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $sheetFound = true;
                $rowIndex = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;
                    $values = $row->toArray();

                    if (! $this->rowHasContent($values)) {
                        continue;
                    }

                    if ($rowIndex === 1) {
                        $headerMap = $this->buildHeaderMap($values);

                        if (! isset($headerMap['nombre'], $headerMap['apellido'])) {
                            throw new \RuntimeException('El Excel debe incluir encabezados "nombre" y "apellido".');
                        }

                        continue;
                    }

                    $parsed = $this->parsePersonaData($values, $headerMap);

                    if ($parsed === null) {
                        $summary['invalidas']++;

                        continue;
                    }

                    $summary['procesadas']++;

                    $persona = $this->resolverPersonaExistente($parsed, $personasPorClave);

                    if ($persona === null) {
                        $summary['ambiguas']++;

                        continue;
                    }

                    if ($persona === false) {
                        $persona = Persona::query()->create([
                            'nombre' => $parsed['nombre'],
                            'apellido' => $parsed['apellido'],
                            'telefono' => $parsed['telefono'],
                            'fecha_nacimiento' => $parsed['fecha_nacimiento'],
                        ]);

                        $summary['personas_nuevas']++;
                        $this->agregarPersonaAlIndice($personasPorClave, $persona);
                    } else {
                        $summary['personas_existentes']++;
                    }

                    $asistencia = Asistencia::query()
                        ->firstOrNew([
                            'persona_id' => $persona->id,
                            'evento_fecha_id' => $this->getRecord()->id,
                        ]);

                    if ($asistencia->exists && $asistencia->presente) {
                        $summary['ya_presentes']++;

                        continue;
                    }

                    $asistencia->presente = true;
                    $asistencia->save();
                    $summary['asistencias_marcadas']++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if (! $sheetFound) {
            throw new \RuntimeException('El archivo no contiene hojas para importar.');
        }

        return $summary;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<string, int>
     */
    protected function buildHeaderMap(array $values): array
    {
        $map = [];

        foreach ($values as $index => $headerValue) {
            $header = $this->normalizeHeader((string) $headerValue);

            $field = match ($header) {
                'nombre', 'nombres', 'name' => 'nombre',
                'apellido', 'apellidos', 'last_name' => 'apellido',
                'telefono', 'celular', 'movil', 'mobile', 'phone', 'telefono_celular' => 'telefono',
                'fecha_nacimiento', 'fecha_de_nacimiento', 'nacimiento', 'birthdate' => 'fecha_nacimiento',
                default => null,
            };

            if ($field !== null) {
                $map[$field] = $index;
            }
        }

        return $map;
    }

    protected function normalizeHeader(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    /**
     * @param  array<int, mixed>  $values
     * @param  array<string, int>  $headerMap
     * @return array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string}|null
     */
    protected function parsePersonaData(array $values, array $headerMap): ?array
    {
        $nombre = trim((string) ($values[$headerMap['nombre']] ?? ''));
        $apellido = trim((string) ($values[$headerMap['apellido']] ?? ''));

        if ($nombre === '' || $apellido === '') {
            return null;
        }

        $telefono = isset($headerMap['telefono']) ? trim((string) ($values[$headerMap['telefono']] ?? '')) : null;
        $telefono = $telefono !== '' ? $telefono : null;

        $fechaNacimiento = null;

        if (isset($headerMap['fecha_nacimiento'])) {
            $fechaNacimiento = $this->parseDate($values[$headerMap['fecha_nacimiento']] ?? null);
        }

        return [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'fecha_nacimiento' => $fechaNacimiento,
        ];
    }

    protected function parseDate(mixed $value): ?string
    {
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
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $values
     */
    protected function rowHasContent(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<int, Persona>>  $index
     * @return array<string, array<int, Persona>>
     */
    protected function indexarPersonasPorNombreApellido(Collection $personas): array
    {
        $index = [];

        foreach ($personas as $persona) {
            $this->agregarPersonaAlIndice($index, $persona);
        }

        return $index;
    }

    /**
     * @param  array<string, array<int, Persona>>  $index
     */
    protected function agregarPersonaAlIndice(array &$index, Persona $persona): void
    {
        $key = $this->personaKey($persona->nombre, $persona->apellido);
        $index[$key] ??= [];
        $index[$key][] = $persona;
    }

    protected function personaKey(string $nombre, string $apellido): string
    {
        return $this->normalizePersonText($nombre).'|'.$this->normalizePersonText($apellido);
    }

    protected function normalizePersonText(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->squish();
    }

    protected function normalizePhone(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== '' ? $digits : null;
    }

    /**
     * @param  array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string}  $parsed
     * @param  array<string, array<int, Persona>>  $personasPorClave
     * @return Persona|false|null
     */
    protected function resolverPersonaExistente(array $parsed, array $personasPorClave): Persona|false|null
    {
        $key = $this->personaKey($parsed['nombre'], $parsed['apellido']);
        $candidates = $personasPorClave[$key] ?? [];

        if ($candidates === []) {
            return false;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $telefono = $this->normalizePhone($parsed['telefono']);
        $fechaNacimiento = $parsed['fecha_nacimiento'];

        if ($telefono !== null) {
            $phoneMatches = array_values(array_filter(
                $candidates,
                fn (Persona $candidate): bool => $this->normalizePhone((string) $candidate->telefono) === $telefono
            ));

            if (count($phoneMatches) === 1) {
                return $phoneMatches[0];
            }

            if (count($phoneMatches) > 1) {
                $candidates = $phoneMatches;
            }
        }

        if ($fechaNacimiento !== null) {
            $dateMatches = array_values(array_filter(
                $candidates,
                fn (Persona $candidate): bool => $this->normalizeCandidateDate($candidate->fecha_nacimiento) === $fechaNacimiento
            ));

            if (count($dateMatches) === 1) {
                return $dateMatches[0];
            }

            if (count($dateMatches) > 1) {
                return null;
            }
        }

        return null;
    }

    protected function normalizeCandidateDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
