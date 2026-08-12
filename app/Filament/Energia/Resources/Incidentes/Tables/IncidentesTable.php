<?php
namespace App\Filament\Energia\Resources\Incidentes\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
class IncidentesTable
{
    public static function configure(Table $table): Table
    {
        return $table->defaultSort('ocorrido_em','desc')->columns([
            TextColumn::make('ocorrido_em')->label('Data e hora')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('tipo')->label('Tipo')->badge()->searchable(), TextColumn::make('causa')->badge(),
            TextColumn::make('distrito.nome')->label('Distrito'), TextColumn::make('severidade')->badge()->sortable(),
            TextColumn::make('situacao')->label('Situação')->badge()->sortable(),
        ])->filters([SelectFilter::make('situacao')->options(['registrado'=>'Registrado','em_atendimento'=>'Em atendimento','resolvido'=>'Resolvido','cancelado'=>'Cancelado'])])
            ->recordActions([EditAction::make()])->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
