<?php

namespace App\Filament\Admin\Resources\RegistroTransitos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistroTransitosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('timestamp', 'desc')
            ->columns([
                TextColumn::make('viaTransito.nome')
                    ->searchable(),
                TextColumn::make('timestamp')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('veiculos_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('velocidade_media')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('altura_media_veiculos')
                    ->label('Altura Média')
                    ->numeric()
                    ->suffix(' m')
                    ->sortable(),
                TextColumn::make('altura_maxima_veiculos')
                    ->label('Altura Máxima')
                    ->numeric()
                    ->suffix(' m')
                    ->sortable(),
                TextColumn::make('nivel_servico')
                    ->searchable(),
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
