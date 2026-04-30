<?php

namespace App\Services;

use App\Models\Grupo;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use App\Models\TipoGrupo;
use Carbon\Carbon;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImportarGruposCsvService
{
    /**
     * @return array{
     *   filas_procesadas:int,
     *   filas_invalidas:int,
     *   grupos_creados:int,
     *   grupos_existentes:int,
     *   personas_nuevas:int,
     *   personas_existentes:int,
     *   coincidencias_telefono:int,
     *   coincidencias_nombre:int,
     *   ambiguas:int,
     *   conflictos_telefono:int,
     *   detalles_ambiguas:array<int, array<string, mixed>>,
     *   detalles_conflictos_telefono:array<int, array<string, mixed>>,
     *   participaciones_creadas:int,
     *   ya_registradas:int
     * }
     */
    public function importar(string $absolutePath, int $anio, string $segmentoEtario, bool $dryRun = false): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("No existe el archivo: {$absolutePath}");
        }

        $callback = function () use ($absolutePath, $anio, $segmentoEtario): array {
            $tipoCrecimiento = TipoGrupo::query()->firstOrCreate(
                ['nombre' => 'Crecimiento'],
                ['descripcion' => 'Creado automaticamente por importacion CSV', 'activo' => true]
            );

            $personas = Persona::query()
                ->select([
                    'id',
                    'nombre',
                    'apellido',
                    'telefono',
                    'telefono_normalizado',
                    'fecha_nacimiento',
                    'departamento',
                    'email',
                ])
                ->get();

            $personasPorClave = $this->indexarPersonasPorNombreApellido($personas);
            $personasPorTelefono = $this->indexarPersonasPorTelefono($personas);
            $gruposPorClave = Grupo::query()
                ->where('anio', $anio)
                ->get()
                ->keyBy(fn (Grupo $grupo): string => $this->grupoKey((string) $grupo->nombre, (int) $grupo->anio));

            $summary = [
                'filas_procesadas' => 0,
                'filas_invalidas' => 0,
                'grupos_creados' => 0,
                'grupos_existentes' => 0,
                'personas_nuevas' => 0,
                'personas_existentes' => 0,
                'coincidencias_telefono' => 0,
                'coincidencias_nombre' => 0,
                'ambiguas' => 0,
                'conflictos_telefono' => 0,
                'detalles_ambiguas' => [],
                'detalles_conflictos_telefono' => [],
                'participaciones_creadas' => 0,
                'ya_registradas' => 0,
            ];

            $handle = fopen($absolutePath, 'rb');

            if ($handle === false) {
                throw new RuntimeException("No se pudo abrir el archivo: {$absolutePath}");
            }

            try {
                $headerMap = null;
                $delimiter = null;

                while (($rawLine = fgets($handle)) !== false) {
                    if ($delimiter === null) {
                        $delimiter = $this->detectDelimiter($rawLine);
                    }

                    $row = str_getcsv($rawLine, $delimiter, '"', '\\');
                    $row = array_map(fn ($value): string => is_string($value) ? trim($value) : '', $row);

                    if (! $this->rowHasContent($row)) {
                        continue;
                    }

                    if ($headerMap === null) {
                        $headerMap = $this->buildHeaderMap($row);

                        if (! isset($headerMap['nombre'], $headerMap['apellido'], $headerMap['grupo'])) {
                            throw new RuntimeException('El CSV debe incluir las columnas apellido, nombre y grupo de crecimiento.');
                        }

                        continue;
                    }

                    $parsed = $this->parsePersonaData($row, $headerMap);

                    if ($parsed === null) {
                        $summary['filas_invalidas']++;

                        continue;
                    }

                    $summary['filas_procesadas']++;

                    $grupoKey = $this->grupoKey($parsed['grupo'], $anio);
                    $grupo = $gruposPorClave->get($grupoKey);

                    if (! $grupo instanceof Grupo) {
                        $grupo = Grupo::query()->create([
                            'nombre' => $parsed['grupo'],
                            'anio' => $anio,
                            'tipo_grupo_id' => $tipoCrecimiento->id,
                            'segmento_etario' => $segmentoEtario,
                            'frecuencia_asistencia' => Grupo::FRECUENCIA_SEMANAL,
                            'descripcion' => 'Grupo creado automaticamente desde importacion CSV',
                            'activo' => true,
                        ]);

                        $gruposPorClave->put($grupoKey, $grupo);
                        $summary['grupos_creados']++;
                    } else {
                        $summary['grupos_existentes']++;
                    }

                    $resolution = $this->resolverPersonaExistente($parsed, $personasPorClave, $personasPorTelefono);
                    $estado = $resolution['estado'];
                    $persona = $resolution['persona'];

                    if ($estado === 'conflicto_telefono') {
                        $summary['conflictos_telefono']++;
                        $summary['detalles_conflictos_telefono'][] = [
                            'csv' => $this->buildPersonaResumen($parsed),
                            'existentes' => array_map(
                                fn (Persona $match): array => $this->buildPersonaResumenDesdeModelo($match),
                                $resolution['matches'] ?? []
                            ),
                        ];

                        continue;
                    }

                    if ($estado === 'ambigua') {
                        $summary['ambiguas']++;
                        $summary['detalles_ambiguas'][] = [
                            'csv' => $this->buildPersonaResumen($parsed),
                            'existentes' => array_map(
                                fn (Persona $match): array => $this->buildPersonaResumenDesdeModelo($match),
                                $resolution['matches'] ?? []
                            ),
                        ];

                        continue;
                    }

                    if ($estado === 'nueva') {
                        $persona = Persona::query()->create([
                            'nombre' => $parsed['nombre'],
                            'apellido' => $parsed['apellido'],
                            'fecha_nacimiento' => $parsed['fecha_nacimiento'],
                            'telefono' => $parsed['telefono'],
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
                        $summary['filas_invalidas']++;

                        continue;
                    }

                    $participacion = ParticipacionGrupo::query()->firstOrNew([
                        'persona_id' => $persona->id,
                        'grupo_id' => $grupo->id,
                        'rol_grupo_id' => null,
                    ]);

                    if ($participacion->exists) {
                        $summary['ya_registradas']++;

                        continue;
                    }

                    $participacion->anio = null;
                    $participacion->save();
                    $summary['participaciones_creadas']++;
                }
            } finally {
                fclose($handle);
            }

            return $summary;
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $summary = $callback();
                DB::rollBack();

                return $summary;
            } catch (Throwable $exception) {
                DB::rollBack();

                throw $exception;
            }
        }

        return DB::transaction($callback);
    }

    /**
     * @param  array{
     *   nombre:string,
     *   apellido:string,
     *   fecha_nacimiento:?string,
     *   telefono:?string,
     *   departamento:?string,
     *   grupo:string
     * }  $parsed
     * @return array<string, mixed>
     */
    protected function buildPersonaResumen(array $parsed): array
    {
        return [
            'apellido' => $parsed['apellido'],
            'nombre' => $parsed['nombre'],
            'fecha_nacimiento' => $parsed['fecha_nacimiento'],
            'telefono' => $parsed['telefono'],
            'departamento' => $parsed['departamento'],
            'grupo' => $parsed['grupo'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPersonaResumenDesdeModelo(Persona $persona): array
    {
        return [
            'id' => $persona->id,
            'apellido' => $persona->apellido,
            'nombre' => $persona->nombre,
            'fecha_nacimiento' => $persona->fecha_nacimiento ? (string) $persona->fecha_nacimiento : null,
            'telefono' => $persona->telefono,
            'departamento' => $persona->departamento,
        ];
    }

    protected function detectDelimiter(string $line): string
    {
        $semicolonCount = substr_count($line, ';');
        $commaCount = substr_count($line, ',');

        return $semicolonCount > $commaCount ? ';' : ',';
    }

    /**
     * @param  array<int, string>  $values
     * @return array<string, int>
     */
    protected function buildHeaderMap(array $values): array
    {
        $map = [];

        foreach ($values as $index => $headerValue) {
            $header = $this->normalizeHeader($headerValue);

            $field = match ($header) {
                'nombre', 'nombres', 'name' => 'nombre',
                'apellido', 'apellidos', 'last_name' => 'apellido',
                'fecha_nacimiento', 'fecha_de_nacimiento', 'fecha_nac', 'nacimiento', 'birthdate' => 'fecha_nacimiento',
                'telefono', 'celular', 'movil', 'mobile', 'phone', 'telefono_celular' => 'telefono',
                'departamento' => 'departamento',
                'grupo', 'grupo_de_crecimiento', 'grupo_crecimiento', 'gc', 'growth_group' => 'grupo',
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
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_');
    }

    /**
     * @param  array<int, string>  $values
     * @param  array<string, int>  $headerMap
     * @return array{
     *   nombre:string,
     *   apellido:string,
     *   fecha_nacimiento:?string,
     *   telefono:?string,
     *   departamento:?string,
     *   grupo:string
     * }|null
     */
    protected function parsePersonaData(array $values, array $headerMap): ?array
    {
        $nombre = trim((string) ($values[$headerMap['nombre']] ?? ''));
        $apellido = trim((string) ($values[$headerMap['apellido']] ?? ''));
        $grupo = trim((string) ($values[$headerMap['grupo']] ?? ''));

        if ($nombre === '' || $apellido === '' || $grupo === '') {
            return null;
        }

        $telefono = isset($headerMap['telefono']) ? trim((string) ($values[$headerMap['telefono']] ?? '')) : null;
        $telefono = $telefono !== '' ? $telefono : null;

        $departamento = isset($headerMap['departamento']) ? trim((string) ($values[$headerMap['departamento']] ?? '')) : null;
        $departamento = $departamento !== '' ? $departamento : null;

        $fechaNacimiento = null;

        if (isset($headerMap['fecha_nacimiento'])) {
            $fechaNacimiento = $this->parseDate($values[$headerMap['fecha_nacimiento']] ?? null);
        }

        return [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'fecha_nacimiento' => $fechaNacimiento,
            'telefono' => $telefono,
            'departamento' => $departamento,
            'grupo' => $grupo,
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
     * @param  array<int, string>  $values
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

    protected function grupoKey(string $nombre, int $anio): string
    {
        return $anio.'|'.$this->normalizePersonText($nombre);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Persona>  $personas
     * @return array<string, array<int, Persona>>
     */
    protected function indexarPersonasPorNombreApellido($personas): array
    {
        $index = [];

        foreach ($personas as $persona) {
            $this->agregarPersonaAlIndice($index, $persona);
        }

        return $index;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Persona>  $personas
     * @return array<string, array<int, Persona>>
     */
    protected function indexarPersonasPorTelefono($personas): array
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
        $key = $this->personaKey((string) $persona->nombre, (string) $persona->apellido);
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
        $key = $this->normalizePhone($persona->telefono_normalizado ?: (string) $persona->telefono);

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

    /**
     * @param  array{
     *   nombre:string,
     *   apellido:string,
     *   fecha_nacimiento:?string,
     *   telefono:?string,
     *   departamento:?string,
     *   grupo:string
     * }  $parsed
     * @param  array<string, array<int, Persona>>  $personasPorClave
     * @param  array<string, array<int, Persona>>  $personasPorTelefono
     * @return array{estado:string,persona:?Persona}
     */
    protected function resolverPersonaExistente(array $parsed, array $personasPorClave, array $personasPorTelefono): array
    {
        $telefono = $this->normalizePhone($parsed['telefono']);

        if ($telefono !== null && isset($personasPorTelefono[$telefono])) {
            $matches = $personasPorTelefono[$telefono];

            if (count($matches) === 1) {
                $persona = $matches[0];

                if ($this->coincideNombreApellido($persona, $parsed['nombre'], $parsed['apellido'])) {
                return ['estado' => 'existente_telefono', 'persona' => $persona, 'matches' => $matches];
            }

                return ['estado' => 'conflicto_telefono', 'persona' => null, 'matches' => $matches];
            }

            return ['estado' => 'conflicto_telefono', 'persona' => null, 'matches' => $matches];
        }

        $key = $this->personaKey($parsed['nombre'], $parsed['apellido']);
        $matches = $personasPorClave[$key] ?? [];

        if ($matches === []) {
            return ['estado' => 'nueva', 'persona' => null, 'matches' => []];
        }

        if (count($matches) === 1) {
            return ['estado' => 'existente_nombre', 'persona' => $matches[0], 'matches' => $matches];
        }

        if ($parsed['fecha_nacimiento']) {
            $byBirthDate = array_values(array_filter(
                $matches,
                fn (Persona $persona): bool => (string) $persona->fecha_nacimiento === $parsed['fecha_nacimiento']
            ));

            if (count($byBirthDate) === 1) {
                return ['estado' => 'existente_nombre', 'persona' => $byBirthDate[0], 'matches' => $matches];
            }
        }

        return ['estado' => 'ambigua', 'persona' => null, 'matches' => $matches];
    }

    /**
     * @param  array{
     *   nombre:string,
     *   apellido:string,
     *   fecha_nacimiento:?string,
     *   telefono:?string,
     *   departamento:?string,
     *   grupo:string
     * }  $parsed
     */
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

        if (($persona->departamento === null || $persona->departamento === '') && filled($parsed['departamento'])) {
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
}
