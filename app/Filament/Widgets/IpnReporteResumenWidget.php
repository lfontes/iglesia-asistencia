<?php

namespace App\Filament\Widgets;

use App\Models\IpnAsistencia;
use App\Models\IpnAula;
use App\Models\IpnAulaPersona;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IpnReporteResumenWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public ?int $ipnAulaId = null;

    public ?int $personaId = null;

    public ?string $desde = null;

    public ?string $hasta = null;

    protected function getStats(): array
    {
        $summary = $this->getSummary();

        return [
            Stat::make('Aula', $summary['aula']?->nombre ?? 'Sin seleccionar')
                ->icon('heroicon-o-academic-cap'),
            Stat::make('Niños', (string) $summary['total_ninos'])
                ->icon('heroicon-o-face-smile'),
            Stat::make('Encuentros', (string) $summary['total_fechas'])
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Promedio', "{$summary['promedio']}%")
                ->color('success')
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    protected function getSummary(): array
    {
        $rows = $this->getRows();
        $dates = $this->getDates();
        $totalPresentes = $rows->sum('presentes');
        $totalPosibles = $rows->count() * $dates->count();

        return [
            'aula' => $this->ipnAulaId && array_key_exists($this->ipnAulaId, $this->aulasOptions())
                ? IpnAula::query()->find($this->ipnAulaId)
                : null,
            'total_ninos' => $rows->count(),
            'total_fechas' => $dates->count(),
            'promedio' => $totalPosibles > 0 ? (int) round(($totalPresentes / $totalPosibles) * 100) : 0,
        ];
    }

    /**
     * @return Collection<int, string>
     */
    protected function getDates(): Collection
    {
        if (! $this->ipnAulaId) {
            return collect();
        }

        if (! array_key_exists($this->ipnAulaId, $this->aulasOptions())) {
            return collect();
        }

        return IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipnAulaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->select('fecha')
            ->distinct()
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn ($date): string => (string) Carbon::parse($date)->toDateString())
            ->values();
    }

    protected function getRows(): Collection
    {
        if (! $this->ipnAulaId) {
            return collect();
        }

        if (! array_key_exists($this->ipnAulaId, $this->aulasOptions())) {
            return collect();
        }

        $asistencias = IpnAsistencia::query()
            ->where('ipn_aula_id', $this->ipnAulaId)
            ->when($this->desde, fn ($query) => $query->whereDate('fecha', '>=', $this->desde))
            ->when($this->hasta, fn ($query) => $query->whereDate('fecha', '<=', $this->hasta))
            ->get()
            ->groupBy('persona_id');

        return IpnAulaPersona::query()
            ->where('ipn_aula_id', $this->ipnAulaId)
            ->when($this->personaId, fn ($query) => $query->where('persona_id', $this->personaId))
            ->get()
            ->unique('persona_id')
            ->map(fn (IpnAulaPersona $participacion): array => [
                'presentes' => collect($asistencias->get($participacion->persona_id, collect()))
                    ->where('presente', true)
                    ->count(),
            ])
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function aulasOptions(): array
    {
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        return $user->ipnAulasDisponibles()
            ->orderBy('nombre')
            ->pluck('nombre', 'id')
            ->all();
    }
}
