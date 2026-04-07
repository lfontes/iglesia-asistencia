<?php

namespace App\Filament\Resources\GrupoResource\Pages;

use App\Filament\Resources\GrupoResource;
use App\Models\ParticipacionGrupo;
use App\Models\Persona;
use App\Models\RolGrupo;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

class RegistrarParticipacion extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;
    use InteractsWithRecord;

    protected static string $resource = GrupoResource::class;

    protected static string $view = 'filament.resources.grupo-resource.pages.registrar-participacion';

    /** @var array<int, int|string> */
    public array $personas = [];

    public ?int $rol_grupo_id = null;

    public ?int $persona_recordatorio_id = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->cargarParticipantes();
        $this->form->fill([
            'rol_grupo_id' => $this->rol_grupo_id,
            'personas' => $this->personas,
            'persona_recordatorio_id' => $this->persona_recordatorio_id,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('importar_participantes')
                ->label('Importar participantes (Excel)')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Select::make('rol_grupo_id')
                        ->label('Rol')
                        ->placeholder('Sin rol')
                        ->searchable()
                        ->options(fn (): array => RolGrupo::query()
                            ->where('activo', true)
                            ->orderBy('nombre')
                            ->pluck('nombre', 'id')
                            ->all())
                        ->default($this->rol_grupo_id),
                    Forms\Components\Toggle::make('reemplazar_actuales')
                        ->label('Reemplazar participantes actuales del rol')
                        ->helperText('Si está activo, se eliminarán participantes actuales de este grupo/rol que no estén en el archivo.')
                        ->default(false),
                    FileUpload::make('archivo_excel')
                        ->label('Archivo Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->directory('imports/participacion-grupos')
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
                        $summary = $this->importarParticipantesDesdeExcel(
                            $absolutePath,
                            $this->normalizarRolGrupoId($data['rol_grupo_id'] ?? null),
                            (bool) ($data['reemplazar_actuales'] ?? false)
                        );
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('No se pudo importar el archivo')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->rol_grupo_id = $this->normalizarRolGrupoId($data['rol_grupo_id'] ?? null);
                    $this->cargarParticipantes();
                    $this->form->fill([
                        'rol_grupo_id' => $this->rol_grupo_id,
                        'personas' => $this->personas,
                        'persona_recordatorio_id' => $this->persona_recordatorio_id,
                    ]);

                    Notification::make()
                        ->title('Importación completada')
                        ->body(implode("\n", [
                            "Filas procesadas: {$summary['procesadas']}",
                            "Personas nuevas: {$summary['personas_nuevas']}",
                            "Personas existentes: {$summary['personas_existentes']}",
                            "Coincidencias por teléfono: {$summary['coincidencias_telefono']}",
                            "Coincidencias por nombre/apellido: {$summary['coincidencias_nombre']}",
                            "Participaciones creadas: {$summary['participaciones_creadas']}",
                            "Ya estaban en el grupo: {$summary['ya_registradas']}",
                            "Participaciones eliminadas por reemplazo: {$summary['participaciones_eliminadas']}",
                            "Filas inválidas: {$summary['invalidas']}",
                            "Coincidencias ambiguas: {$summary['ambiguas']}",
                            "Conflictos por teléfono: {$summary['conflictos_telefono']}",
                        ]))
                        ->success()
                        ->send();
                }),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('rol_grupo_id')
                ->label('Rol')
                ->placeholder('Sin rol')
                ->searchable()
                ->options(fn (): array => RolGrupo::query()
                    ->where('activo', true)
                    ->orderBy('nombre')
                    ->pluck('nombre', 'id')
                    ->all())
                ->live()
                ->afterStateUpdated(fn () => $this->cargarParticipantes()),

            Forms\Components\Select::make('personas')
                ->label('Personas')
                ->multiple()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state): void {
                    $personaIds = collect($state ?? [])
                        ->filter(fn ($id): bool => filled($id))
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    if ($this->persona_recordatorio_id !== null && ! in_array($this->persona_recordatorio_id, $personaIds, true)) {
                        $this->persona_recordatorio_id = null;
                        $this->form->fill([
                            'rol_grupo_id' => $this->rol_grupo_id,
                            'personas' => $personaIds,
                            'persona_recordatorio_id' => $this->persona_recordatorio_id,
                        ]);
                    }
                })
                ->preload(false)
                ->getSearchResultsUsing(fn (string $search): array => Persona::query()
                    ->buscarPorNombreApellido($search)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->limit(50)
                    ->get()
                    ->mapWithKeys(fn (Persona $persona): array => [
                        $persona->id => $this->personaLabel($persona),
                    ])
                    ->all())
                ->getOptionLabelsUsing(fn (array $values): array => Persona::query()
                    ->whereIn('id', $values)
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get()
                    ->mapWithKeys(fn (Persona $persona): array => [
                        $persona->id => $this->personaLabel($persona),
                    ])
                    ->all()),

            Forms\Components\Select::make('persona_recordatorio_id')
                ->label('Recibe recordatorios')
                ->placeholder('Nadie seleccionado')
                ->helperText('Cuando el rol es facilitador, este participante será el destinatario principal del recordatorio del grupo.')
                ->options(fn (): array => Persona::query()
                    ->whereIn('id', collect($this->personas)->filter()->map(fn ($id): int => (int) $id)->all())
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get()
                    ->mapWithKeys(fn (Persona $persona): array => [
                        $persona->id => $this->personaLabel($persona),
                    ])
                    ->all())
                ->visible(fn (): bool => $this->esRolFacilitadorSeleccionado())
                ->live(),
        ]);
    }

    public function cargarParticipantes(): void
    {
        $rolGrupoId = $this->normalizarRolGrupoId($this->rol_grupo_id);

        $this->personas = ParticipacionGrupo::query()
            ->where('grupo_id', $this->getRecord()->id)
            ->when(
                $rolGrupoId !== null,
                fn ($query) => $query->where('rol_grupo_id', $rolGrupoId),
                fn ($query) => $query->whereNull('rol_grupo_id')
            )
            ->pluck('persona_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $this->persona_recordatorio_id = ParticipacionGrupo::query()
            ->where('grupo_id', $this->getRecord()->id)
            ->where('recibe_recordatorios', true)
            ->when(
                $rolGrupoId !== null,
                fn ($query) => $query->where('rol_grupo_id', $rolGrupoId),
                fn ($query) => $query->whereNull('rol_grupo_id')
            )
            ->value('persona_id');
    }

    public function guardar(): void
    {
        $grupo = $this->getRecord();
        $rolGrupoId = $this->normalizarRolGrupoId($this->rol_grupo_id);

        $personaIds = collect($this->personas)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $personaRecordatorioId = filled($this->persona_recordatorio_id) ? (int) $this->persona_recordatorio_id : null;

        if ($personaRecordatorioId !== null && ! $personaIds->contains($personaRecordatorioId)) {
            $personaRecordatorioId = null;
            $this->persona_recordatorio_id = null;
        }

        ParticipacionGrupo::query()
            ->where('grupo_id', $grupo->id)
            ->when(
                $rolGrupoId !== null,
                fn ($query) => $query->where('rol_grupo_id', $rolGrupoId),
                fn ($query) => $query->whereNull('rol_grupo_id')
            )
            ->when(
                $personaIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('persona_id', $personaIds->all())
            )
            ->delete();

        /** @var int $personaId */
        foreach ($personaIds as $personaId) {
            ParticipacionGrupo::updateOrCreate(
                [
                    'persona_id' => $personaId,
                    'grupo_id' => $grupo->id,
                    'rol_grupo_id' => $rolGrupoId,
                ],
                [
                    'anio' => null,
                    'recibe_recordatorios' => $this->esRolFacilitadorSeleccionado() && $personaRecordatorioId === $personaId,
                ]
            );
        }

        if ($this->esRolFacilitadorSeleccionado()) {
            ParticipacionGrupo::query()
                ->where('grupo_id', $grupo->id)
                ->when(
                    $rolGrupoId !== null,
                    fn ($query) => $query->where('rol_grupo_id', $rolGrupoId),
                    fn ($query) => $query->whereNull('rol_grupo_id')
                )
                ->when(
                    $personaRecordatorioId !== null,
                    fn ($query) => $query->where('persona_id', '!=', $personaRecordatorioId)
                )
                ->update(['recibe_recordatorios' => false]);
        }

        $this->cargarParticipantes();
        $this->form->fill([
            'rol_grupo_id' => $this->rol_grupo_id,
            'personas' => $this->personas,
            'persona_recordatorio_id' => $this->persona_recordatorio_id,
        ]);

        Notification::make()
            ->title('Participacion guardada correctamente')
            ->success()
            ->send();
    }

    protected function personaLabel(Persona $persona): string
    {
        return trim("{$persona->apellido} {$persona->nombre}");
    }

    protected function normalizarRolGrupoId(int | string | null $valor): ?int
    {
        return filled($valor) ? (int) $valor : null;
    }

    protected function esRolFacilitadorSeleccionado(): bool
    {
        if (! filled($this->rol_grupo_id)) {
            return false;
        }

        $nombre = (string) RolGrupo::query()
            ->whereKey($this->normalizarRolGrupoId($this->rol_grupo_id))
            ->value('nombre');

        return Str::contains(Str::lower($nombre), 'facilit');
    }

    /**
     * @return array{
     *     procesadas:int,
     *     personas_nuevas:int,
     *     personas_existentes:int,
     *     coincidencias_telefono:int,
     *     coincidencias_nombre:int,
     *     participaciones_creadas:int,
     *     ya_registradas:int,
     *     participaciones_eliminadas:int,
     *     invalidas:int,
     *     ambiguas:int,
     *     conflictos_telefono:int
     * }
     */
    protected function importarParticipantesDesdeExcel(string $absolutePath, ?int $rolGrupoId, bool $reemplazarActuales = false): array
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
            'participaciones_creadas' => 0,
            'ya_registradas' => 0,
            'participaciones_eliminadas' => 0,
            'invalidas' => 0,
            'ambiguas' => 0,
            'conflictos_telefono' => 0,
        ];
        $personaIdsImportados = [];

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

                    $personaIdsImportados[] = $persona->id;

                    $participacion = ParticipacionGrupo::query()
                        ->firstOrNew([
                            'persona_id' => $persona->id,
                            'grupo_id' => $this->getRecord()->id,
                            'rol_grupo_id' => $rolGrupoId,
                        ]);

                    if ($participacion->exists) {
                        $summary['ya_registradas']++;
                        continue;
                    }

                    $participacion->anio = null;
                    $participacion->save();
                    $summary['participaciones_creadas']++;
                }

                break;
            }
        } finally {
            $reader->close();
        }

        if ($reemplazarActuales) {
            $personaIdsImportados = collect($personaIdsImportados)
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();

            $deleteQuery = ParticipacionGrupo::query()
                ->where('grupo_id', $this->getRecord()->id)
                ->when(
                    $rolGrupoId !== null,
                    fn ($query) => $query->where('rol_grupo_id', $rolGrupoId),
                    fn ($query) => $query->whereNull('rol_grupo_id')
                );

            if ($personaIdsImportados->isNotEmpty()) {
                $deleteQuery->whereNotIn('persona_id', $personaIdsImportados->all());
            }

            $summary['participaciones_eliminadas'] = $deleteQuery->delete();
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
                'fecha_nacimiento', 'fecha_de_nacimiento', 'fecha_nac', 'nacimiento', 'birthdate' => 'fecha_nacimiento',
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

        $text = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y'] as $format) {
            $date = \DateTime::createFromFormat($format, $text);

            if ($date instanceof \DateTimeInterface) {
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

    /**
     * @param  array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string}  $parsed
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

        return $dirty;
    }

    protected function coincideNombreApellido(Persona $persona, string $nombre, string $apellido): bool
    {
        return $this->normalizePersonText((string) $persona->nombre) === $this->normalizePersonText($nombre)
            && $this->normalizePersonText((string) $persona->apellido) === $this->normalizePersonText($apellido);
    }

    /**
     * @param  array{nombre:string,apellido:string,telefono:?string,fecha_nacimiento:?string}  $parsed
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
