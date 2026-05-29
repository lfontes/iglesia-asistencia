<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PersonaResource;
use App\Models\PersonaParIgnorado;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Radio;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class PersonasDuplicadas extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?string $navigationLabel = 'Posibles duplicados';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'Posibles personas duplicadas';

    protected string $view = 'filament.pages.personas-duplicadas';

    public array $pares = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole(['admin', 'secretario']) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        try {
            $this->cargarPares();
        } catch (\Throwable $e) {
            Notification::make()->danger()
                ->title('Error al cargar duplicados')
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    public function cargarPares(): void
    {
        $ignorados = PersonaParIgnorado::query()
            ->selectRaw('CONCAT(persona_a_id, "-", persona_b_id) AS clave')
            ->pluck('clave')
            ->flip();

        $sql = <<<'SQL'
            SELECT
                a.id AS id_a, a.nombre AS nombre_a, a.apellido AS apellido_a, a.telefono AS telefono_a,
                b.id AS id_b, b.nombre AS nombre_b, b.apellido AS apellido_b, b.telefono AS telefono_b
            FROM (
                SELECT id, nombre, apellido, telefono,
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        LOWER(TRIM(CONCAT(TRIM(nombre), ' ', TRIM(apellido)))),
                    'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ü','u'),'ñ','n') AS fn
                FROM personas
            ) a
            JOIN (
                SELECT id, nombre, apellido, telefono,
                    REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        LOWER(TRIM(CONCAT(TRIM(nombre), ' ', TRIM(apellido)))),
                    'á','a'),'é','e'),'í','i'),'ó','o'),'ú','u'),'ü','u'),'ñ','n') AS fn
                FROM personas
            ) b ON a.id < b.id
            WHERE (
                (LENGTH(a.fn) <= LENGTH(b.fn)
                 AND LOCATE(' ', a.fn) > 0
                 AND b.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(a.fn, ' ', 1), '( |$)')
                 AND b.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(a.fn, ' ', 2), ' ', -1), '( |$)')
                 AND (LENGTH(a.fn) - LENGTH(REPLACE(a.fn, ' ', '')) < 2
                      OR b.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(a.fn, ' ', 3), ' ', -1), '( |$)'))
                 AND (LENGTH(a.fn) - LENGTH(REPLACE(a.fn, ' ', '')) < 3
                      OR b.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(a.fn, ' ', 4), ' ', -1), '( |$)'))
                )
                OR
                (LENGTH(b.fn) < LENGTH(a.fn)
                 AND LOCATE(' ', b.fn) > 0
                 AND a.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(b.fn, ' ', 1), '( |$)')
                 AND a.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(b.fn, ' ', 2), ' ', -1), '( |$)')
                 AND (LENGTH(b.fn) - LENGTH(REPLACE(b.fn, ' ', '')) < 2
                      OR a.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(b.fn, ' ', 3), ' ', -1), '( |$)'))
                 AND (LENGTH(b.fn) - LENGTH(REPLACE(b.fn, ' ', '')) < 3
                      OR a.fn REGEXP CONCAT('(^| )', SUBSTRING_INDEX(SUBSTRING_INDEX(b.fn, ' ', 4), ' ', -1), '( |$)'))
                )
            )
            ORDER BY a.apellido, a.nombre
        SQL;

        $this->pares = collect(DB::select($sql))
            ->filter(fn ($par) => ! isset($ignorados["{$par->id_a}-{$par->id_b}"]))
            ->map(fn ($par) => (array) $par)
            ->values()
            ->all();
    }

    public function ignorarPar(int $idA, int $idB): void
    {
        $this->mountAction('ignorar', ['id_a' => $idA, 'id_b' => $idB]);
    }

    public function abrirFusionar(int $idA, int $idB): void
    {
        $par = collect($this->pares)->first(
            fn ($p) => $p['id_a'] === $idA && $p['id_b'] === $idB
        );

        if ($par) {
            $this->mountAction('fusionar', $par);
        }
    }

    public function ignorarAction(): Action
    {
        return Action::make('ignorar')
            ->label('Ignorar')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Ignorar par')
            ->modalDescription('Este par no volverá a aparecer en la lista.')
            ->modalSubmitActionLabel('Confirmar')
            ->action(function (array $arguments): void {
                PersonaParIgnorado::firstOrCreate([
                    'persona_a_id' => min($arguments['id_a'], $arguments['id_b']),
                    'persona_b_id' => max($arguments['id_a'], $arguments['id_b']),
                ]);
                $this->cargarPares();
                Notification::make()->success()->title('Par ignorado')->send();
            });
    }

    public function fusionarAction(): Action
    {
        return Action::make('fusionar')
            ->label('Fusionar')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->color('warning')
            ->modalHeading('Fusionar personas')
            ->modalDescription('La persona que no conserves será eliminada y todas sus relaciones pasarán a la que conservás.')
            ->modalSubmitActionLabel('Fusionar')
            ->schema(fn (array $arguments): array => [
                Radio::make('keep_id')
                    ->label('¿Cuál persona conservar?')
                    ->options([
                        $arguments['id_a'] => "{$arguments['nombre_a']} {$arguments['apellido_a']}" . ($arguments['telefono_a'] ? " — {$arguments['telefono_a']}" : '') . "  (ID {$arguments['id_a']})",
                        $arguments['id_b'] => "{$arguments['nombre_b']} {$arguments['apellido_b']}" . ($arguments['telefono_b'] ? " — {$arguments['telefono_b']}" : '') . "  (ID {$arguments['id_b']})",
                    ])
                    ->default($arguments['id_a'])
                    ->required(),
            ])
            ->action(function (array $data, array $arguments): void {
                $keepId = (int) $data['keep_id'];
                $deleteId = $keepId === (int) $arguments['id_a']
                    ? (int) $arguments['id_b']
                    : (int) $arguments['id_a'];

                Artisan::call('personas:merge', [
                    'keep_id'       => $keepId,
                    'duplicate_ids' => [$deleteId],
                ]);

                $this->cargarPares();

                Notification::make()->success()
                    ->title('Personas fusionadas')
                    ->body("Se conservó el ID {$keepId}.")
                    ->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalcular')
                ->label('Recalcular')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->cargarPares()),
        ];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function getEditUrl(int $personaId): string
    {
        return PersonaResource::getUrl('edit', ['record' => $personaId]);
    }
}
