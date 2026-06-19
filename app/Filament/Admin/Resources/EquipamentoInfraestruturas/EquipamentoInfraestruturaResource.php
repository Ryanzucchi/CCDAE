<?php

namespace App\Filament\Admin\Resources\EquipamentoInfraestruturas;

use App\Filament\Admin\Resources\EquipamentoInfraestruturas\Pages\CreateEquipamentoInfraestrutura;
use App\Filament\Admin\Resources\EquipamentoInfraestruturas\Pages\EditEquipamentoInfraestrutura;
use App\Filament\Admin\Resources\EquipamentoInfraestruturas\Pages\ListEquipamentoInfraestruturas;
use App\Filament\Admin\Resources\EquipamentoInfraestruturas\Schemas\EquipamentoInfraestruturaForm;
use App\Filament\Admin\Resources\EquipamentoInfraestruturas\Tables\EquipamentoInfraestruturasTable;
use App\Models\EquipamentoInfraestrutura;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EquipamentoInfraestruturaResource extends Resource
{
    protected static ?string $model = EquipamentoInfraestrutura::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Infraestrutura Urbana';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    public static function form(Schema $schema): Schema
    {
        return EquipamentoInfraestruturaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipamentoInfraestruturasTable::configure($table);
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
            'index' => ListEquipamentoInfraestruturas::route('/'),
            'create' => CreateEquipamentoInfraestrutura::route('/create'),
            'edit' => EditEquipamentoInfraestrutura::route('/{record}/edit'),
        ];
    }
}
