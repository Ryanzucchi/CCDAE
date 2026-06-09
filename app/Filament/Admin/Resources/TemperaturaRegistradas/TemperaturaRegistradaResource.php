<?php

namespace App\Filament\Admin\Resources\TemperaturaRegistradas;

use App\Filament\Admin\Resources\TemperaturaRegistradas\Pages\ManageTemperaturaRegistradas;
use App\Models\TemperaturaRegistrada;
use Filament\Resources\Resource;
use BackedEnum;
use UnitEnum;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TemperaturaRegistradaResource extends Resource
{
    protected static ?string $model = TemperaturaRegistrada::class;
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedSun;
    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';
    protected static ?string $modelLabel = 'Temperatura';

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(null)->columns([
                TextColumn::make('distrito.nome')
                    ->label('Distrito')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('timestamp')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('temperatura')
                    ->label('Temp (°C)')
                    ->badge()
                    ->color(fn ($state) => $state > 30 ? 'danger' : ($state < 15 ? 'info' : 'success'))
                    ->sortable(),
            ])
            ->defaultKeySort(false)->defaultSort('timestamp', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTemperaturaRegistradas::route('/'),
        ];
    }
}
