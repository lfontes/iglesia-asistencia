<?php

namespace App\Filament\Widgets;

use Spatie\Activitylog\Models\Activity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Activity::query()
                    ->with('causer', 'subject')
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Acción')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Usuario')
                    ->default('Sistema')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('event')
                    ->label('Evento')
                    ->colors([
                        'success' => 'created',
                        'info' => 'updated',
                        'danger' => 'deleted',
                        'warning' => 'restored',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5]);
    }

    public static function getHeading(): string
    {
        return 'Actividad Reciente';
    }

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(['admin', 'director_ipn']) ?? false;
    }
}
