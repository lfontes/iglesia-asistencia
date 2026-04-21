<?php

namespace App\Filament\Resources\PersonaResource\Pages;

use App\Filament\Resources\PersonaResource;
use App\Filament\Widgets\PersonaPerfilStatsWidget;
use App\Models\Asistencia;
use App\Models\IpnAulaPersona;
use App\Models\IpnAulaServidor;
use App\Models\ParticipacionGrupo;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;

class ViewPersona extends ViewRecord
{
    protected static string $resource = PersonaResource::class;

    public string $periodo = 'actual';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $periodo = request()->query('periodo');

        if ($periodo === 'actual' || (is_numeric($periodo) && (int) $periodo >= 2000 && (int) $periodo <= ((int) date('Y') + 1))) {
            $this->periodo = (string) $periodo;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\SelectAction::make('periodo')
                ->label('Periodo')
                ->options(fn (): array => $this->getPeriodoOptions()),
            Actions\EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PersonaPerfilStatsWidget::make([
                'recordId' => $this->record->id,
                'periodo' => $this->periodo,
            ]),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Datos de la persona')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextEntry::make('nombre_completo')
                            ->label('Persona')
                            ->state(fn (): string => trim($this->record->apellido.' '.$this->record->nombre))
                            ->weight('bold')
                            ->columnSpan(2),
                        TextEntry::make('id')
                            ->label('ID')
                            ->badge(),
                        TextEntry::make('telefono')
                            ->label('Teléfono')
                            ->default('Sin teléfono'),
                        TextEntry::make('email')
                            ->label('Email')
                            ->default('Sin email'),
                        TextEntry::make('departamento')
                            ->label('Departamento')
                            ->default('Sin departamento'),
                        TextEntry::make('edad')
                            ->label('Edad')
                            ->state(fn (): ?string => $this->record->edad !== null ? "{$this->record->edad} años" : null)
                            ->default('-'),
                    ])
                    ->columns(3),

                Section::make('Crecimiento')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (): bool => $this->getParticipacionesCrecimiento()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('crecimiento')
                            ->hiddenLabel()
                            ->state(fn (): array => $this->getCrecimientoRows())
                            ->schema([
                                TextEntry::make('grupo')
                                    ->label('Grupo')
                                    ->weight('medium'),
                                TextEntry::make('rol')
                                    ->label('Rol')
                                    ->badge(),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'gray'),
                                TextEntry::make('fecha_inicio')
                                    ->label('Inicio')
                                    ->default('-'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Ministerios')
                    ->icon('heroicon-o-users')
                    ->visible(fn (): bool => $this->getParticipacionesMinisterio()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('ministerios')
                            ->hiddenLabel()
                            ->state(fn (): array => $this->getMinisterioRows())
                            ->schema([
                                TextEntry::make('grupo')
                                    ->label('Grupo')
                                    ->weight('medium'),
                                TextEntry::make('tipo')
                                    ->label('Tipo'),
                                TextEntry::make('rol')
                                    ->label('Rol')
                                    ->badge(),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'gray'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('IPN')
                    ->icon('heroicon-o-academic-cap')
                    ->visible(fn (): bool => $this->getIpnServidor()->isNotEmpty() || $this->getIpnNino()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('ipn_servidor')
                            ->label('Como maestro / servidor')
                            ->visible(fn (): bool => $this->getIpnServidor()->isNotEmpty())
                            ->state(fn (): array => $this->getIpnServidorRows())
                            ->schema([
                                TextEntry::make('aula')
                                    ->label('Aula')
                                    ->weight('medium'),
                                TextEntry::make('rol')
                                    ->label('Rol')
                                    ->badge(),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'gray'),
                            ])
                            ->columns(3),
                        RepeatableEntry::make('ipn_nino')
                            ->label('Como niño/a IPN')
                            ->visible(fn (): bool => $this->getIpnNino()->isNotEmpty())
                            ->state(fn (): array => $this->getIpnNinoRows())
                            ->schema([
                                TextEntry::make('aula')
                                    ->label('Aula')
                                    ->weight('medium'),
                                TextEntry::make('estado')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'Activo' ? 'success' : 'gray'),
                            ])
                            ->columns(2),
                    ])
                    ->columns(2),

                Section::make('Eventos')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (): bool => $this->getEventos()->isNotEmpty())
                    ->schema([
                        RepeatableEntry::make('eventos')
                            ->hiddenLabel()
                            ->state(fn (): array => $this->getEventoRows())
                            ->schema([
                                TextEntry::make('evento')
                                    ->label('Evento')
                                    ->weight('medium'),
                                TextEntry::make('fecha')
                                    ->label('Fecha')
                                    ->default('-'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public function updatedPeriodo(): void
    {
        $this->redirect(static::getResource()::getUrl('view', [
            'record' => $this->record,
            'periodo' => $this->periodo,
        ]));
    }

    public function getPeriodoLabel(): string
    {
        return $this->periodo === 'actual'
            ? 'Perfil actual'
            : "Perfil durante {$this->periodo}";
    }

    /**
     * @return array<string, string>
     */
    public function getPeriodoOptions(): array
    {
        $currentYear = (int) date('Y');
        $years = range($currentYear, max($currentYear - 5, 2000));

        return ['actual' => 'Actual'] + collect($years)
            ->mapWithKeys(fn (int $year): array => [(string) $year => (string) $year])
            ->all();
    }

    /**
     * @return Collection<int, ParticipacionGrupo>
     */
    public function getParticipacionesCrecimiento(): Collection
    {
        return $this->participacionesGrupoBase()
            ->whereHas('grupo.tipoGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['crecimiento']))
            ->get()
            ->sortBy(fn (ParticipacionGrupo $participacion): string => (string) $participacion->grupo?->nombre)
            ->values();
    }

    /**
     * @return Collection<int, ParticipacionGrupo>
     */
    public function getParticipacionesMinisterio(): Collection
    {
        return $this->participacionesGrupoBase()
            ->where(function ($query): void {
                $query->whereDoesntHave('grupo.tipoGrupo', fn ($typeQuery) => $typeQuery->whereRaw('LOWER(nombre) = ?', ['crecimiento']))
                    ->orWhereHas('grupo', fn ($groupQuery) => $groupQuery->whereNull('tipo_grupo_id'));
            })
            ->get()
            ->sortBy(fn (ParticipacionGrupo $participacion): string => (string) $participacion->grupo?->nombre)
            ->values();
    }

    /**
     * @return Collection<int, IpnAulaServidor>
     */
    public function getIpnServidor(): Collection
    {
        return $this->applyPeriodo(
            IpnAulaServidor::query()
                ->with('aula:id,nombre')
                ->where('persona_id', $this->record->id)
        )
            ->get()
            ->sortBy(fn (IpnAulaServidor $servidor): string => (string) $servidor->aula?->nombre)
            ->values();
    }

    /**
     * @return Collection<int, IpnAulaPersona>
     */
    public function getIpnNino(): Collection
    {
        return $this->applyPeriodo(
            IpnAulaPersona::query()
                ->with('aula:id,nombre')
                ->where('persona_id', $this->record->id)
        )
            ->get()
            ->sortBy(fn (IpnAulaPersona $participacion): string => (string) $participacion->aula?->nombre)
            ->values();
    }

    /**
     * @return Collection<int, Asistencia>
     */
    public function getEventos(): Collection
    {
        return Asistencia::query()
            ->with('eventoFecha.evento:id,nombre')
            ->where('persona_id', $this->record->id)
            ->where('presente', true)
            ->when($this->periodo !== 'actual', function ($query): void {
                $query->whereHas('eventoFecha', fn ($eventDateQuery) => $eventDateQuery->whereYear('fecha', (int) $this->periodo));
            })
            ->latest('id')
            ->limit($this->periodo === 'actual' ? 10 : 100)
            ->get();
    }

    public function getCrecimientoRows(): array
    {
        return $this->getParticipacionesCrecimiento()
            ->map(fn (ParticipacionGrupo $row): array => [
                'grupo' => $row->grupo?->nombre ?? '-',
                'rol' => $this->roleBadge($row->rolGrupo?->nombre),
                'estado' => $this->estadoLabel($row),
                'fecha_inicio' => $row->fecha_inicio?->format('d/m/Y') ?? '-',
            ])
            ->all();
    }

    public function getMinisterioRows(): array
    {
        return $this->getParticipacionesMinisterio()
            ->map(fn (ParticipacionGrupo $row): array => [
                'grupo' => $row->grupo?->nombre ?? '-',
                'tipo' => $this->tipoGrupoLabel($row->grupo?->tipoGrupo?->nombre),
                'rol' => $this->roleBadge($row->rolGrupo?->nombre),
                'estado' => $this->estadoLabel($row),
            ])
            ->all();
    }

    public function getIpnServidorRows(): array
    {
        return $this->getIpnServidor()
            ->map(fn (IpnAulaServidor $row): array => [
                'aula' => $row->aula?->nombre ?? '-',
                'rol' => $row->rol ?: 'Servidor',
                'estado' => $this->estadoLabel($row),
            ])
            ->all();
    }

    public function getIpnNinoRows(): array
    {
        return $this->getIpnNino()
            ->map(fn (IpnAulaPersona $row): array => [
                'aula' => $row->aula?->nombre ?? '-',
                'estado' => $this->estadoLabel($row),
            ])
            ->all();
    }

    public function getEventoRows(): array
    {
        return $this->getEventos()
            ->map(fn (Asistencia $row): array => [
                'evento' => $row->eventoFecha?->evento?->nombre ?? '-',
                'fecha' => $row->eventoFecha?->fecha
                    ? \Illuminate\Support\Carbon::parse($row->eventoFecha->fecha)->format('d/m/Y')
                    : '-',
            ])
            ->all();
    }

    public function estadoLabel($record): string
    {
        if (($record->activo ?? true) === false) {
            return 'Inactivo';
        }

        if ($record->fecha_fin && $record->fecha_fin->lt(now()->startOfDay())) {
            return 'Finalizado';
        }

        return 'Activo';
    }

    public function roleBadge(?string $rol): string
    {
        return filled($rol) ? (string) $rol : 'Participante';
    }

    protected function participacionesGrupoBase()
    {
        return $this->applyPeriodo(
            ParticipacionGrupo::query()
                ->with(['grupo.tipoGrupo:id,nombre', 'rolGrupo:id,nombre'])
                ->where('persona_id', $this->record->id)
        );
    }

    protected function applyPeriodo($query)
    {
        if ($this->periodo === 'actual') {
            return $query->vigenteEnFecha(now()->toDateString());
        }

        return $query->vigenteEnAnio((int) $this->periodo);
    }

    public function tipoGrupoLabel(?string $tipo): string
    {
        return filled($tipo) ? (string) $tipo : 'Sin tipo';
    }
}
