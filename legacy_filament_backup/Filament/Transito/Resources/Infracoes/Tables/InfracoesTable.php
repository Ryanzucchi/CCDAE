<?php

namespace App\Filament\Transito\Resources\Infracoes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InfracoesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('ocorrido_em', 'desc')->columns([
            TextColumn::make('ocorrido_em')->label('Data e hora')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('tipo')->searchable()->sortable(),
            TextColumn::make('placa')->searchable(),
            TextColumn::make('via.nome')->label('Via')->searchable(),
            TextColumn::make('distrito.nome')->label('Distrito')->searchable(),
            TextColumn::make('valor')->money('BRL')->sortable(),
            TextColumn::make('pontos')->numeric()->sortable(),
            TextColumn::make('situacao')->label('Situação')->badge()->sortable(),
        ])->filters([
            SelectFilter::make('situacao')->label('Situação')->options([
                'registrada' => 'Registrada', 'notificada' => 'Notificada',
                'cancelada' => 'Cancelada', 'paga' => 'Paga',
            ]),
        ])->recordActions([EditAction::make()])->toolbarActions([
            BulkActionGroup::make([DeleteBulkAction::make()]),
        ]);
    }
}
