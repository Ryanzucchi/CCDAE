<?php

namespace App\Filament\Admin\Resources\ParticulaArs;

use App\Filament\Admin\Resources\ParticulaArs\Pages\ManageParticulaArs;
use App\Models\ParticulaAr;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ParticulaArResource extends Resource
{
    protected static ?string $model = ParticulaAr::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';

    protected static ?string $modelLabel = 'Partículas do Ar';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction(null)->columns([
                TextColumn::make('distrito.nome')->label('Distrito')->sortable()->searchable(),
                TextColumn::make('timestamp')->label('Data/Hora')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('pm25')->label('PM2.5 (µg/m³)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('pm10')->label('PM10 (µg/m³)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('poeira')->label('Poeira (µg/m³)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('areia')->label('Areia (µg/m³)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
                TextColumn::make('poluentes')->label('Ozônio (µg/m³)')
                    ->numeric(decimalPlaces: 1)
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultKeySort(false)->defaultSort('timestamp', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageParticulaArs::route('/'),
        ];
    }
}
