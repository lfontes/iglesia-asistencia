<?php

namespace App\Filament\Pages;

use App\Models\WhatsAppMessage;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

class WhatsAppConversaciones extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static string | \UnitEnum | null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Conversaciones';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Conversaciones WhatsApp';

    protected string $view = 'filament.pages.whatsapp-conversaciones';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole('admin');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->heading('Conversaciones')
            ->description('Mensajes entrantes y salientes agrupados por contacto.')
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no hay conversaciones registradas')
            ->emptyStateDescription('Cuando lleguen o envíes mensajes, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-chat-bubble-bottom-center-text')
            ->columns([
                TextColumn::make('contacto')
                    ->label('Contacto')
                    ->state(fn (WhatsAppMessage $record): string => $record->persona ? trim($record->persona->apellido.' '.$record->persona->nombre) : ($record->from_phone ?: $record->to_phone ?: (string) $record->conversation_key))
                    ->searchable(['from_phone', 'to_phone', 'conversation_key'])
                    ->weight('medium'),
                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->state(fn (WhatsAppMessage $record): string => $record->from_phone ?: $record->to_phone ?: (string) $record->conversation_key)
                    ->searchable(['from_phone', 'to_phone']),
                TextColumn::make('grupo.nombre')
                    ->label('Grupo')
                    ->placeholder('-'),
                TextColumn::make('body')
                    ->label('Último mensaje')
                    ->limit(80)
                    ->placeholder('Sin contenido de texto'),
                TextColumn::make('no_leidos_count')
                    ->label('Sin leer')
                    ->badge()
                    ->color(fn (int|string|null $state): string => ((int) $state) > 0 ? 'warning' : 'gray')
                    ->alignCenter(),
                TextColumn::make('ventana_estado')
                    ->label('Ventana')
                    ->state(function (WhatsAppMessage $record): string {
                        $lastInboundAt = $record->last_inbound_at;

                        if (! $lastInboundAt) {
                            return 'Requiere plantilla';
                        }

                        return now()->diffInHours(Carbon::parse($lastInboundAt)) < 24
                            ? 'Ventana abierta'
                            : 'Requiere plantilla';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Ventana abierta' ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Último momento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('abrir')
                    ->label('Abrir conversación')
                    ->icon('heroicon-o-eye')
                    ->url(fn (WhatsAppMessage $record): string => WhatsAppConversacion::getUrl(['key' => $record->conversation_key])),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return WhatsAppMessage::query()
            ->select('whatsapp_messages.*')
            ->with(['persona:id,nombre,apellido', 'grupo:id,nombre'])
            ->whereNotNull('conversation_key')
            ->whereIn('id', function (QueryBuilder $query): void {
                $query->from('whatsapp_messages')
                    ->selectRaw('MAX(id)')
                    ->whereNotNull('conversation_key')
                    ->groupBy('conversation_key');
            })
            ->selectSub(
                WhatsAppMessage::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('conversation_key', 'whatsapp_messages.conversation_key')
                    ->where('direction', 'inbound')
                    ->whereNull('read_in_app_at'),
                'no_leidos_count'
            )
            ->selectSub(
                WhatsAppMessage::query()
                    ->selectRaw('MAX(created_at)')
                    ->whereColumn('conversation_key', 'whatsapp_messages.conversation_key')
                    ->where('direction', 'inbound'),
                'last_inbound_at'
            );
    }
}
