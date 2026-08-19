<?php

namespace App\Filament\Energia\Resources\Incidentes;

use App\Filament\Energia\Resources\Incidentes\Pages\CreateIncidente;
use App\Filament\Energia\Resources\Incidentes\Pages\EditIncidente;
use App\Filament\Energia\Resources\Incidentes\Pages\ListIncidentes;
use App\Filament\Energia\Resources\Incidentes\Schemas\IncidenteForm;
use App\Filament\Energia\Resources\Incidentes\Tables\IncidentesTable;
use App\Models\IncidenteEnergia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IncidenteEnergiaResource extends Resource
{
    protected static ?string $model = IncidenteEnergia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Surtos e incidentes';

    protected static ?string $modelLabel = 'incidente de energia';

    protected static ?string $pluralModelLabel = 'surtos e incidentes';

    protected static ?string $slug = 'incidentes';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return IncidenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IncidentesTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListIncidentes::route('/'), 'create' => CreateIncidente::route('/create'), 'edit' => EditIncidente::route('/{record}/edit')];
    }
}
