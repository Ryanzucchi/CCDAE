<?php

namespace App\Filament\Admin\Resources\ActivityLogs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Horário')
                    ->dateTime('H:i:s')
                    ->sortable()
                    ->description(fn ($record) => $record->created_at->format('d/m/Y')),

                TextColumn::make('description')
                    ->label('Evento')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info', // 'warning' também é uma ótima opção
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                TextColumn::make('subject_type')
                    ->label('Tabela/Model')
                    ->formatStateUsing(fn ($state) => str_replace('App\Models\\', '', $state))
                    ->searchable(),

                TextColumn::make('subject_id')
                    ->label('ID do Registro')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('causer.name')
                    ->label('Usuário')
                    ->default('Sistema')
                    ->searchable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('2s') // Removida a vírgula extra aqui
            ->filters([
                // Remova o TrashedFilter se sua tabela de logs não usar SoftDeletes
                // TrashedFilter::make(),
            ])
            ->actions([
                ViewAction::make(),
                // Geralmente logs não são editáveis, mas se precisar:
                // EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
