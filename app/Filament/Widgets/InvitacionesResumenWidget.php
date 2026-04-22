<?php

namespace App\Filament\Widgets;

use App\Services\InvitationAudienceBuilder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvitacionesResumenWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    /** @var array<int, int|string> */
    public array $eventoFechaIdsOrigen = [];

    /** @var array<int, int|string> */
    public array $grupoIdsOrigen = [];

    public ?int $eventoFechaIdDestino = null;

    public bool $excluirSinTelefono = true;

    public bool $excluirYaAsistieronDestino = true;

    public bool $excluirYaInvitadosDestino = true;

    protected function getStats(): array
    {
        $stats = app(InvitationAudienceBuilder::class)->build($this->getFilters())['stats'];

        return [
            Stat::make('Personas únicas', (string) $stats['total_unicos'])
                ->icon('heroicon-o-users'),
            Stat::make('Sin teléfono', (string) $stats['sin_telefono'])
                ->color($stats['sin_telefono'] > 0 ? 'warning' : 'success')
                ->icon($stats['sin_telefono'] > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
            Stat::make('Ya asistieron', (string) $stats['ya_asistieron_destino'])
                ->color('info')
                ->icon('heroicon-o-check-badge'),
            Stat::make('Ya invitados', (string) $stats['ya_invitados'])
                ->color('gray')
                ->icon('heroicon-o-paper-airplane'),
            Stat::make('Destinatarios finales', (string) $stats['finales'])
                ->color('success')
                ->icon('heroicon-o-user-plus'),
        ];
    }

    protected function getFilters(): array
    {
        return [
            'evento_fecha_ids_origen' => $this->eventoFechaIdsOrigen,
            'grupo_ids_origen' => $this->grupoIdsOrigen,
            'evento_fecha_id_destino' => $this->eventoFechaIdDestino,
            'excluir_sin_telefono' => $this->excluirSinTelefono,
            'excluir_ya_asistieron_destino' => $this->excluirYaAsistieronDestino,
            'excluir_ya_invitados_destino' => $this->excluirYaInvitadosDestino,
        ];
    }
}
