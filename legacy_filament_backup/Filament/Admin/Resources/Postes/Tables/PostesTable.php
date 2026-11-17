<?php

namespace App\Filament\Admin\Resources\Postes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('distrito_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('codigo_patrimonio')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('material')
                    ->searchable(),
                TextColumn::make('altura_metros')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('resistencia_kg')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('possui_iluminacao')
                    ->boolean(),
                TextColumn::make('data_instalacao')
                    ->date()
                    ->sortable(),
                TextColumn::make('ultima_manutencao')
                    ->date()
                    ->sortable(),
                TextColumn::make('estado_conservacao')
                    ->searchable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
