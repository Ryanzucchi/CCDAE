<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ViaTransitosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('distrito.id')
                    ->searchable(),
                TextColumn::make('nome')
                    ->searchable(),
                TextColumn::make('nivel_congestionamento')
                    ->searchable(),
                TextColumn::make('velocidade_media')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('volume_veiculos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('impacto_manutencao')
                    ->searchable(),
                TextColumn::make('ultima_atualizacao')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
