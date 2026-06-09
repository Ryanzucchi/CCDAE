<?php

namespace App\Filament\Admin\Resources\PressaoAtmosfericas;

use App\Filament\Admin\Resources\PressaoAtmosfericas\Pages\ManagePressaoAtmosfericas;
use App\Models\PressaoAtmosferica;
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

class PressaoAtmosfericaResource extends Resource
{
    protected static ?string $model = PressaoAtmosferica::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';

    protected static ?string $modelLabel = 'Pressão Atmosférica';

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
                TextColumn::make('pressao_hpa')
                    ->label('Pressão (hPa)')
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
            'index' => ManagePressaoAtmosfericas::route('/'),
        ];
    }
}
