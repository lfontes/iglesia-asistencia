<?php

namespace App\Filament\Widgets;

use App\Models\Asistencia;
use App\Models\IpnAulaPersona;
use App\Models\IpnAulaServidor;
use App\Models\ParticipacionGrupo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PersonaPerfilStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public int|string|null $recordId = null;

    public string $periodo = 'actual';

    protected function getStats(): array
    {
        if (! $this->recordId) {
            return [];
        }

        $crecimiento = $this->participacionesGrupoBase()
            ->whereHas('grupo.tipoGrupo', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['crecimiento']))
            ->get();

        $ministerios = $this->participacionesGrupoBase()
            ->where(function ($query): void {
                $query->whereDoesntHave('grupo.tipoGrupo', fn ($typeQuery) => $typeQuery->whereRaw('LOWER(nombre) = ?', ['crecimiento']))
                    ->orWhereHas('grupo', fn ($groupQuery) => $groupQuery->whereNull('tipo_grupo_id'));
            })
            ->get();

        $ipnServidor = $this->applyPeriodo(
            IpnAulaServidor::query()->where('persona_id', $this->recordId)
        )->count();

        $ipnNino = $this->applyPeriodo(
            IpnAulaPersona::query()->where('persona_id', $this->recordId)
        )->count();

        $eventos = Asistencia::query()
            ->where('persona_id', $this->recordId)
            ->where('presente', true)
            ->when($this->periodo !== 'actual', function ($query): void {
                $query->whereHas('eventoFecha', fn ($eventDateQuery) => $eventDateQuery->whereYear('fecha', (int) $this->periodo));
            })
            ->count();

        $stats = [
            Stat::make('Crecimiento', (string) $crecimiento->count())
                ->color('success')
                ->icon('heroicon-o-sparkles'),
            Stat::make('Ministerios', (string) $ministerios->count())
                ->color('warning')
                ->icon('heroicon-o-users'),
            Stat::make('IPN', (string) ($ipnServidor + $ipnNino))
                ->color('info')
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Eventos asistidos', (string) $eventos)
                ->color('danger')
                ->icon('heroicon-o-calendar-days'),
        ];

        $visibleStats = collect($stats)
            ->filter(fn (Stat $stat): bool => (int) $stat->getValue() > 0)
            ->values()
            ->all();

        if ($visibleStats !== []) {
            return $visibleStats;
        }

        return [
            Stat::make('Sin participación registrada', '0')
                ->icon('heroicon-o-clipboard-document-list'),
        ];
    }

    protected function participacionesGrupoBase()
    {
        return $this->applyPeriodo(
            ParticipacionGrupo::query()
                ->with(['grupo.tipoGrupo:id,nombre', 'rolGrupo:id,nombre'])
                ->where('persona_id', $this->recordId)
        );
    }

    protected function applyPeriodo($query)
    {
        if ($this->periodo === 'actual') {
            return $query->vigenteEnFecha(now()->toDateString());
        }

        return $query->vigenteEnAnio((int) $this->periodo);
    }
}
