<?php

namespace App\Filament\Admin\Resources\Cabeamentos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CabeamentosTable
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
                TextColumn::make('tipo_cabo')
                    ->searchable(),
                TextColumn::make('capacidade')
                    ->searchable(),
                TextColumn::make('revestimento')
                    ->searchable(),
                IconColumn::make('subterraneo')
                    ->boolean(),
                TextColumn::make('extensao_metros')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('poste_origem_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('poste_destino_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('central_origem_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('central_destino_id')
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
