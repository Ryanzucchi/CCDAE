<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CentralDistribuicaosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('distrito_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable(),
                TextColumn::make('codigo_patrimonio')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->searchable(),
                TextColumn::make('capacidade')
                    ->searchable(),
                TextColumn::make('area_m2')
                    ->numeric()
                    ->sortable(),
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
