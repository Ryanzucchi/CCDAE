<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos;

use App\Filament\Admin\Resources\CentralDistribuicaos\Pages\CreateCentralDistribuicao;
use App\Filament\Admin\Resources\CentralDistribuicaos\Pages\EditCentralDistribuicao;
use App\Filament\Admin\Resources\CentralDistribuicaos\Pages\ListCentralDistribuicaos;
use App\Filament\Admin\Resources\CentralDistribuicaos\Schemas\CentralDistribuicaoForm;
use App\Filament\Admin\Resources\CentralDistribuicaos\Tables\CentralDistribuicaosTable;
use App\Models\CentralDistribuicao;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CentralDistribuicaoResource extends Resource
{
    protected static ?string $model = CentralDistribuicao::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Infraestrutura Urbana';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function form(Schema $schema): Schema
    {
        return CentralDistribuicaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CentralDistribuicaosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCentralDistribuicaos::route('/'),
            'create' => CreateCentralDistribuicao::route('/create'),
            'edit' => EditCentralDistribuicao::route('/{record}/edit'),
        ];
    }
}
