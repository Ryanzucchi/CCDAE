<?php

namespace App\Filament\Admin\Resources\IndiceUVS;

use App\Filament\Admin\Resources\IndiceUVS\Pages\ManageIndiceUVS;
use App\Models\IndiceUV;
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

class IndiceUVResource extends Resource
{
    protected static ?string $model = IndiceUV::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';

    protected static ?string $modelLabel = 'Índice UV';

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
                TextColumn::make('uv')
                    ->label('Índice UV')
                    ->numeric(decimalPlaces: 1)
                    ->badge()
                    ->color(fn ($state) => $state >= 8 ? 'danger' : ($state >= 6 ? 'warning' : ($state >= 3 ? 'success' : 'info')))
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
            'index' => ManageIndiceUVS::route('/'),
        ];
    }
}
