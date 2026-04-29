<?php

namespace App\Filament\Resources\EventoFechaResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use RuntimeException;
use DateTimeInterface;
use DateTime;
use App\Filament\Resources\EventoFechaResource;
use App\Models\Asistencia;
use App\Models\EventoInscripcion;
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
            Action::make('importar_inscriptos')
                ->label('Importar inscriptos (Excel)')
                ->icon('heroicon-o-document-arrow-up')
                ->schema([
                    FileUpload::make('archivo_excel')
                        ->label('Archivo Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->directory('imports/inscripciones-evento')
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
                        $summary = $this->importarInscriptosDesdeExcel($absolutePath);
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
                        ->title('Importación de inscriptos completada')
                        ->body(implode("\n", [
                            "Filas procesadas: {$summary['procesadas']}",
                            "Personas nuevas: {$summary['personas_nuevas']}",
                            "Personas existentes: {$summary['personas_existentes']}",
                            "Coincidencias por teléfono: {$summary['coincidencias_telefono']}",
                            "Coincidencias por nombre/apellido: {$summary['coincidencias_nombre']}",
                            "Inscriptos nuevos: {$summary['inscripciones_creadas']}",
                            "Ya estaban inscriptos: {$summary['ya_inscriptos']}",
                            "Filas inválidas: {$summary['invalidas']}",
                            "Coincidencias ambiguas: {$summary['ambiguas']}",
                            "Conflictos por teléfono: {$summary['conflictos_telefono']}",
                        ]))
                        ->success()
                        ->send();
                }),
            Action::make('importar_presentes')
                ->label('Importar presentes (Excel)')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
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
                            "Coincidencias por teléfono: {$summary['coincidencias_telefono']}",
                            "Coincidencias por nombre/apellido: {$summary['coincidencias_nombre']}",
                            "Asistencias marcadas: {$summary['asistencias_marcadas']}",
                            "Ya estaban presentes: {$summary['ya_presentes']}",
                            "Filas inválidas: {$summary['invalidas']}",
                            "Coincidencias ambiguas: {$summary['ambiguas']}",
                            "Conflictos por teléfono: {$summary['conflictos_telefono']}",
                        ]))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array{
     *     procesadas:int,
     *     personas_nuevas:int,
     *     personas_existentes:int,
     *     coincidencias_telefono:int,
     *     coincidencias_nombre:int,
     *     inscripciones_creadas:int,
     *     ya_inscriptos:int,
     *     invalidas:int,
     *     ambiguas:int,
     *     conflictos_telefono:int
     * }
     */
    protected function importarInscriptosDesdeExcel(string $absolutePath): array
    {
        $personas = Persona::query()
            ->select(['id', 'nombre', 'apellido', 'telefono', 'fecha_nacimiento', 'email', 'departamento'])
            ->get();

        $personasPorClave = $this->indexarPersonasPorNombreApellido($personas);
        $personasPorTelefono = $this->indexarPersonasPorTelefono($personas);
        $headerMap = [];
        $sheetFound = false;
        $summary = [
            'procesadas' => 0,
            'personas_nuevas' => 0,
            'personas_existentes' => 0,
            'coincidencias_telefono' => 0,
            'coincidencias_nombre' => 0,
            'inscripciones_creadas' => 0,
            'ya_inscriptos' => 0,
            'invalidas' => 0,
            'ambiguas' => 0,
            'conflictos_telefono' => 0,
        ];

        $reader = new XlsxReader;
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
                            throw new RuntimeException('El Excel debe incluir encabezados "nombre" y "apellido".');
                        }

                        continue;
                    }

                    $parsed = $this->parsePersonaData($values, $headerMap);

                    if ($parsed === null) {
                        $summary['invalidas']++;

                        continue;
                    }

                    $summary['procesadas']++;

                    $resolution = $this->resolverPersonaExistente($parsed, $personasPorClave, $personasPorTelefono);
                    $estado = $resolution['estado'];
                    $persona = $resolution['persona'];

                    if ($estado === 'conflicto_telefono') {
                        $summary['conflictos_telefono']++;

                        continue;
                    }

                    if ($estado === 'ambigua') {
                        $summary['ambiguas']++;

                        continue;
                    }

                    if ($estado === 'nueva') {
                        $persona = Persona::query()->create([
                            'nombre' => $parsed['nombre'],
                            'apellido' => $parsed['apellido'],
                            'telefono' => $parsed['telefono'],
                            'fecha_nacimiento' => $parsed['fecha_nacimiento'],
                            'email' => $parsed['email'],
                            'departamento' => $parsed['departamento'],
                        ]);

                        $summary['personas_nuevas']++;
                        $this->agregarPersonaAlIndice($personasPorClave, $persona);
                        $this->agregarPersonaAlIndiceTelefono($personasPorTelefono, $persona);
                    } else {
                        $summary['personas_existentes']++;
                        if ($estado === 'existente_telefono') {
                            $summary['coincidencias_telefono']++;
                        }

                        if ($estado === 'existente_nombre') {
                            $summary['coincidencias_nombre']++;
                        }

                        if ($persona instanceof Persona && $this->completarDatosPersona($persona, $parsed)) {
                            $persona->save();
                            $this->agregarPersonaAlIndiceTelefono($personasPorTelefono, $persona);
                        }
                    }

                    if (! $persona instanceof Persona) {
                        $summary['invalidas']++;

                        continue;
                    }

                    $inscripcion = EventoInscripcion::query()
                        ->firstOrNew([
                            'persona_id' => $persona->id,
                            'evento_fecha_id' => $this->getRecord()->id,
                        ]);

                    $inscripcion->estado = 'inscripto';
                    $inscripcion->datos_capturados = array_filter([
                        'nombre' => $parsed['nombre'],
                        'apellido' => $parsed['apellido'],
                        'telefono' => $parsed['telefono'],
                        'fecha_nacimiento' => $parsed['fecha_nacimiento'],
                        'email' => $parsed['email'],
                        'departamento' => $parsed['departamento'],
                    ], fn (mixed $value): bool => $value !== null && $value !== '');

                    if ($inscripcion->exists) {
                        $inscripcion->save();
                        $summary['ya_inscriptos']++;

                        continue;
                    }

                    $inscripcion->save();
                    $summary['inscripciones_creadas']++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if (! $sheetFound) {
            throw new RuntimeException('El archivo no contiene hojas para importar.');
        }

        return $summary;
    }

    /**
     * @return array{
     *     procesadas:int,
     *     personas_nuevas:int,
     *     personas_existentes:int,
     *     coincidencias_telefono:int,
     *     coincidencias_nombre:int,
     *     asistencias_marcadas:int,
     *     ya_presentes:int,
     *     invalidas:int,
     *     ambiguas:int,
     *     conflictos_telefono:int
     * }
     */
    protected function importarPresentesDesdeExcel(string $absolutePath): array
    {
        $personas = Persona::query()
            ->select(['id', 'nombre', 'apellido', 'telefono', 'fecha_nacimiento'])
            ->get();

        $personasPorClave = $this->indexarPersonasPorNombreApellido($personas);
        $personasPorTelefono = $this->indexarPersonasPorTelefono($personas);
        $headerMap = [];
        $sheetFound = false;
        $summary = [
            'procesadas' => 0,
            'personas_nuevas' => 0,
            'personas_existentes' => 0,
            'coincidencias_telefono' => 0,
            'coincidencias_nombre' => 0,
            'asistencias_marcadas' => 0,
            'ya_presentes' => 0,
            'invalidas' => 0,
            'ambiguas' => 0,
            'conflictos_telefono' => 0,
        ];

        $reader = new XlsxReader;
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
                            throw new RuntimeException('El Excel debe incluir encabezados "nombre" y "apellido".');
                        }

                        continue;
                    }

                    $parsed = $this->parsePersonaData($values, $headerMap);

                    if ($parsed === null) {
                        $summary['invalidas']++;

                        continue;
                    }

                    $summary['procesadas']++;

                    $resolution = $this->resolverPersonaExistente($parsed, $personasPorClave, $personasPorTelefono);
                    $estado = $resolution['estado'];
                    $persona = $resolution['persona'];

                    if ($estado === 'conflicto_telefono') {
                        $summary['conflictos_telefono']++;

                        continue;
                    }

                    if ($estado === 'ambigua') {
                        $summary['ambiguas']++;

                        continue;
                    }

                    if ($estado === 'nueva') {
                        $persona = Persona::query()->create([
                            'nombre' => $parsed['nombre'],
                            'apellido' => $parsed['apellido'],
                            'telefono' => $parsed['telefono'],
                            'fecha_nacimiento' => $parsed['fecha_nacimiento'],
                        ]);

                        $summary['personas_nuevas']++;
                        $this->agregarPersonaAlIndice($personasPorClave, $persona);
                        $this->agregarPersonaAlIndiceTelefono($personasPorTelefono, $persona);
                    } else {
                        $summary['personas_existentes']++;
                        if ($estado === 'existente_telefono') {
                            $summary['coincidencias_telefono']++;
                        }

                        if ($estado === 'existente_nombre') {
                            $summary['coincidencias_nombre']++;
                        }

                        if ($persona instanceof Persona && $this->completarDatosPersona($persona, $parsed)) {
                            $persona->save();
                            $this->agregarPersonaAlIndiceTelefono($personasPorTelefono, $persona);
                        }
                    }

                    if (! $persona instanceof Persona) {
                        $summary['invalidas']++;

                        continue;
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
            throw new RuntimeException('El archivo no contiene hojas para importar.');
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
                'fecha_nacimiento', 'fecha_de_nacimiento', 'fecha_nac', 'nacimiento', 'birthdate' => 'fecha_nacimiento',
                'email', 'correo', 'mail', 'correo_electronico' => 'email',
                'departamento' => 'departamento',
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
     * @return array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string,email:?string,departamento:?string}|null
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
        $email = isset($headerMap['email']) ? trim((string) ($values[$headerMap['email']] ?? '')) : null;
        $email = $email !== '' ? $email : null;
        $departamento = isset($headerMap['departamento']) ? trim((string) ($values[$headerMap['departamento']] ?? '')) : null;
        $departamento = $departamento !== '' ? $departamento : null;

        $fechaNacimiento = null;

        if (isset($headerMap['fecha_nacimiento'])) {
            $fechaNacimiento = $this->parseDate($values[$headerMap['fecha_nacimiento']] ?? null);
        }

        return [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'telefono' => $telefono,
            'fecha_nacimiento' => $fechaNacimiento,
            'email' => $email,
            'departamento' => $departamento,
        ];
    }

    protected function parseDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
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

        $text = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $format) {
            $date = DateTime::createFromFormat($format, $text);

            if ($date instanceof DateTimeInterface) {
                return $date->format('Y-m-d');
            }
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
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
     * @return array<string, array<int, Persona>>
     */
    protected function indexarPersonasPorTelefono(Collection $personas): array
    {
        $index = [];

        foreach ($personas as $persona) {
            $this->agregarPersonaAlIndiceTelefono($index, $persona);
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
        if (! in_array($persona->id, array_map(static fn (Persona $item): int => $item->id, $index[$key]), true)) {
            $index[$key][] = $persona;
        }
    }

    /**
     * @param  array<string, array<int, Persona>>  $index
     */
    protected function agregarPersonaAlIndiceTelefono(array &$index, Persona $persona): void
    {
        $key = $this->normalizePhone((string) $persona->telefono);

        if ($key === null) {
            return;
        }

        $index[$key] ??= [];
        if (! in_array($persona->id, array_map(static fn (Persona $item): int => $item->id, $index[$key]), true)) {
            $index[$key][] = $persona;
        }
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

    protected function completarDatosPersona(Persona $persona, array $parsed): bool
    {
        $dirty = false;

        if (($persona->telefono === null || $persona->telefono === '') && filled($parsed['telefono'])) {
            $persona->telefono = $parsed['telefono'];
            $dirty = true;
        }

        if (($persona->fecha_nacimiento === null || $persona->fecha_nacimiento === '') && filled($parsed['fecha_nacimiento'])) {
            $persona->fecha_nacimiento = $parsed['fecha_nacimiento'];
            $dirty = true;
        }

        if (($persona->email === null || $persona->email === '') && filled($parsed['email'] ?? null)) {
            $persona->email = $parsed['email'];
            $dirty = true;
        }

        if (($persona->departamento === null || $persona->departamento === '') && filled($parsed['departamento'] ?? null)) {
            $persona->departamento = $parsed['departamento'];
            $dirty = true;
        }

        return $dirty;
    }

    protected function coincideNombreApellido(Persona $persona, string $nombre, string $apellido): bool
    {
        return $this->normalizePersonText((string) $persona->nombre) === $this->normalizePersonText($nombre)
            && $this->normalizePersonText((string) $persona->apellido) === $this->normalizePersonText($apellido);
    }

    /**
     * @param  array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string,email:?string,departamento:?string}  $parsed
     * @param  array<string, array<int, Persona>>  $personasPorClave
     * @param  array<string, array<int, Persona>>  $personasPorTelefono
     * @return array{estado:'nueva'|'existente_telefono'|'existente_nombre'|'ambigua'|'conflicto_telefono', persona:?Persona}
     */
    protected function resolverPersonaExistente(array $parsed, array $personasPorClave, array $personasPorTelefono): array
    {
        $telefono = $this->normalizePhone($parsed['telefono']);

        if ($telefono !== null) {
            $phoneCandidates = $personasPorTelefono[$telefono] ?? [];

            if (count($phoneCandidates) === 1) {
                $candidate = $phoneCandidates[0];

                if (! $this->coincideNombreApellido($candidate, $parsed['nombre'], $parsed['apellido'])) {
                    return ['estado' => 'conflicto_telefono', 'persona' => null];
                }

                return ['estado' => 'existente_telefono', 'persona' => $candidate];
            }

            if (count($phoneCandidates) > 1) {
                return ['estado' => 'conflicto_telefono', 'persona' => null];
            }
        }

        $key = $this->personaKey($parsed['nombre'], $parsed['apellido']);
        $candidates = $personasPorClave[$key] ?? [];

        if ($candidates === []) {
            return ['estado' => 'nueva', 'persona' => null];
        }

        if (count($candidates) === 1) {
            return ['estado' => 'existente_nombre', 'persona' => $candidates[0]];
        }

        $fechaNacimiento = $parsed['fecha_nacimiento'];

        if ($telefono !== null) {
            $phoneMatches = array_values(array_filter(
                $candidates,
                fn (Persona $candidate): bool => $this->normalizePhone((string) $candidate->telefono) === $telefono
            ));

            if (count($phoneMatches) === 1) {
                return ['estado' => 'existente_telefono', 'persona' => $phoneMatches[0]];
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
                return ['estado' => 'existente_nombre', 'persona' => $dateMatches[0]];
            }

            if (count($dateMatches) > 1) {
                return ['estado' => 'ambigua', 'persona' => null];
            }
        }

        return ['estado' => 'ambigua', 'persona' => null];
    }

    protected function normalizeCandidateDate(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
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
