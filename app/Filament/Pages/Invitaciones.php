<?php

namespace App\Filament\Pages;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Collection;
use App\Jobs\SendInvitationCampaignJob;
use App\Services\InvitationAudienceBuilder;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Invitaciones extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | \UnitEnum | null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Invitaciones';

    protected static ?int $navigationSort = 31;

    protected static ?string $title = 'Invitaciones';

    protected string $view = 'filament.pages.invitaciones';

    /** @var array<int, int|string> */
    public array $evento_fecha_ids_origen = [];

    /** @var array<int, int|string> */
    public array $grupo_ids_origen = [];

    public ?int $evento_fecha_id_destino = null;

    public bool $excluir_sin_telefono = true;

    public bool $excluir_ya_asistieron_destino = true;

    public bool $excluir_ya_invitados_destino = true;

    public function mount(): void
    {
        $this->form->fill([
            'evento_fecha_ids_origen' => $this->evento_fecha_ids_origen,
            'grupo_ids_origen' => $this->grupo_ids_origen,
            'evento_fecha_id_destino' => $this->evento_fecha_id_destino,
            'excluir_sin_telefono' => $this->excluir_sin_telefono,
            'excluir_ya_asistieron_destino' => $this->excluir_ya_asistieron_destino,
            'excluir_ya_invitados_destino' => $this->excluir_ya_invitados_destino,
        ]);
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function form(Schema $schema): Schema
    {
        $builder = app(InvitationAudienceBuilder::class);

        return $schema->components([
            Section::make('Fuentes de audiencia')
                ->schema([
                    Select::make('evento_fecha_ids_origen')
                        ->label('Asistentes de eventos')
                        ->options($builder->getEventoFechaOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('grupo_ids_origen')
                        ->label('Miembros de grupos')
                        ->options($builder->getGrupoOptions())
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->live(),
                ])
                ->columns(2),
            Section::make('Destino y reglas')
                ->schema([
                    Select::make('evento_fecha_id_destino')
                        ->label('Evento destino')
                        ->options($builder->getEventoFechaOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    Toggle::make('excluir_sin_telefono')
                        ->label('Excluir sin telefono')
                        ->live(),
                    Toggle::make('excluir_ya_asistieron_destino')
                        ->label('Excluir quienes ya asistieron al destino')
                        ->live(),
                    Toggle::make('excluir_ya_invitados_destino')
                        ->label('Excluir ya invitados al destino')
                        ->live(),
                ])
                ->columns(2),
        ]);
    }

    /**
     * @return array{rows: Collection, deliverable_people: Collection, stats: array<string, int>}
     */
    public function getAudiencePreview(): array
    {
        return app(InvitationAudienceBuilder::class)->build($this->getFilters());
    }

    /**
     * @return array{
     *   evento_fecha_ids_origen:array<int, int|string>,
     *   grupo_ids_origen:array<int, int|string>,
     *   evento_fecha_id_destino:?int,
     *   excluir_sin_telefono:bool,
     *   excluir_ya_asistieron_destino:bool,
     *   excluir_ya_invitados_destino:bool
     * }
     */
    protected function getFilters(): array
    {
        return [
            'evento_fecha_ids_origen' => $this->evento_fecha_ids_origen,
            'grupo_ids_origen' => $this->grupo_ids_origen,
            'evento_fecha_id_destino' => $this->evento_fecha_id_destino,
            'excluir_sin_telefono' => $this->excluir_sin_telefono,
            'excluir_ya_asistieron_destino' => $this->excluir_ya_asistieron_destino,
            'excluir_ya_invitados_destino' => $this->excluir_ya_invitados_destino,
        ];
    }

    public function enviarInvitaciones(): void
    {
        $hasSource = $this->evento_fecha_ids_origen !== [] || $this->grupo_ids_origen !== [];

        if (! $hasSource) {
            Notification::make()
                ->title('Falta definir la audiencia')
                ->body('Selecciona al menos un evento origen o un grupo origen.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->evento_fecha_id_destino) {
            Notification::make()
                ->title('Falta el evento destino')
                ->body('Selecciona a qué evento quieres invitar.')
                ->warning()
                ->send();

            return;
        }

        $preview = $this->getAudiencePreview();

        if (($preview['stats']['finales'] ?? 0) === 0) {
            Notification::make()
                ->title('No hay destinatarios elegibles')
                ->body('La audiencia quedó vacía después de aplicar las exclusiones.')
                ->warning()
                ->send();

            return;
        }

        SendInvitationCampaignJob::dispatch($this->getFilters(), auth()->id());

        Notification::make()
            ->title('Invitaciones en cola')
            ->body("Se encoló la campaña para {$preview['stats']['finales']} personas.")
            ->success()
            ->send();
    }
}
