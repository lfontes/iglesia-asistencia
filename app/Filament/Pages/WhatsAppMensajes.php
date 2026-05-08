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

class WhatsAppMensajes extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-oval-left-ellipsis';

    protected static string | \UnitEnum | null $navigationGroup = 'WhatsApp';

    protected static ?string $navigationLabel = 'Mensajes WhatsApp';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Mensajes WhatsApp';

    protected string $view = 'filament.pages.whatsapp-mensajes';

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
            ->heading('Historial de mensajes')
            ->description('Estados recibidos desde Meta para pruebas y envíos manuales.')
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Aún no hay mensajes registrados')
            ->emptyStateDescription('Cuando se registren mensajes de WhatsApp, aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-chat-bubble-oval-left-ellipsis')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('use_case')
                    ->label('Uso')
                    ->state(fn (WhatsAppMessage $record): string => $record->use_case ?: 'Sin uso')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('contexto')
                    ->label('Contexto')
                    ->state(function (WhatsAppMessage $record): string {
                        $partes = [];

                        if ($record->grupo?->nombre) {
                            $partes[] = $record->grupo->nombre;
                        }

                        if ($record->persona) {
                            $partes[] = trim(($record->persona->apellido ?? '').' '.($record->persona->nombre ?? ''));
                        }

                        if ($record->periodo_inicio && $record->periodo_fin) {
                            $partes[] = $record->periodo_inicio->format('d/m/Y').' - '.$record->periodo_fin->format('d/m/Y');
                        }

                        return implode(' | ', array_filter($partes)) ?: '-';
                    })
                    ->wrap(),
                TextColumn::make('conversation_key')
                    ->label('Conversación')
                    ->state(fn (WhatsAppMessage $record): string => $record->conversation_key ? 'Abrir conversación' : 'Sin clave')
                    ->url(fn (WhatsAppMessage $record): ?string => $record->conversation_key ? WhatsAppConversacion::getUrl(['key' => $record->conversation_key]) : null)
                    ->color(fn (WhatsAppMessage $record): ?string => $record->conversation_key ? 'primary' : 'gray')
                    ->openUrlInNewTab(false),
                TextColumn::make('destino')
                    ->label('Destino')
                    ->state(function (WhatsAppMessage $record): string {
                        $telefono = $record->to_phone ?: $record->from_phone ?: 'Sin número';

                        return $record->recipient_wa_id
                            ? "{$telefono} | wa_id: {$record->recipient_wa_id}"
                            : $telefono;
                    })
                    ->searchable(['to_phone', 'from_phone', 'recipient_wa_id'])
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'info',
                        'sent' => 'info',
                        'delivered' => 'success',
                        'read' => 'success',
                        'failed', 'failed_request' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('body')
                    ->label('Mensaje')
                    ->wrap()
                    ->searchable()
                    ->limit(120),
                TextColumn::make('provider_message_id')
                    ->label('Meta ID')
                    ->state(fn (WhatsAppMessage $record): string => $record->provider_message_id ?: 'Sin ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('error_message')
                    ->label('Detalle')
                    ->state(fn (WhatsAppMessage $record): string => $record->error_message ?: 'Sin novedades')
                    ->wrap()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('abrirConversacion')
                    ->label('Abrir conversación')
                    ->icon('heroicon-o-eye')
                    ->url(fn (WhatsAppMessage $record): string => WhatsAppConversacion::getUrl(['key' => $record->conversation_key]))
                    ->visible(fn (WhatsAppMessage $record): bool => filled($record->conversation_key)),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return WhatsAppMessage::query()
            ->with(['persona:id,nombre,apellido', 'grupo:id,nombre'])
            ->latest('id');
    }
}
