<?php

namespace App\Filament\Pages;

use App\Models\Grupo;
use App\Services\AsistenciasPendientesService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class AsistenciasPendientes extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = 'Grupos';

    protected static ?string $navigationLabel = 'Asistencias pendientes';

    protected static ?int $navigationSort = 18;

    protected static ?string $title = 'Asistencias pendientes';

    protected static string $view = 'filament.pages.asistencias-pendientes';

    public ?string $fecha = null;

    public function mount(): void
    {
        $this->form->fill([
            'fecha' => $this->fecha ?? now()->toDateString(),
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('fecha')
                ->label('Fecha de referencia')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->live()
                ->required(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hoy')
                ->label('Usar hoy')
                ->color('gray')
                ->action(function (): void {
                    $this->fecha = now()->toDateString();
                    $this->form->fill(['fecha' => $this->fecha]);
                }),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getPendientes(): Collection
    {
        return app(AsistenciasPendientesService::class)
            ->obtener($this->getFechaReferencia());
    }

    /**
     * @return array{total_grupos:int,total_facilitadores:int,sin_telefono:int,semanales:int,quincenales:int,mensuales:int}
     */
    public function getSummary(): array
    {
        $pendientes = $this->getPendientes();

        return [
            'total_grupos' => $pendientes->count(),
            'total_facilitadores' => $pendientes->sum(fn (array $item): int => count($item['facilitadores'])),
            'sin_telefono' => $pendientes->sum(
                fn (array $item): int => collect($item['facilitadores'])->filter(fn (array $f): bool => blank($f['telefono']))->count()
            ),
            'semanales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_SEMANAL)->count(),
            'quincenales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_QUINCENAL)->count(),
            'mensuales' => $pendientes->where('frecuencia', Grupo::FRECUENCIA_MENSUAL)->count(),
        ];
    }

    protected function getFechaReferencia(): Carbon
    {
        try {
            return filled($this->fecha)
                ? Carbon::parse((string) $this->fecha)->startOfDay()
                : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }
}
