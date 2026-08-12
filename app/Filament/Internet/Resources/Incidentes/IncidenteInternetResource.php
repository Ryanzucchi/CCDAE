<?php
namespace App\Filament\Internet\Resources\Incidentes;
use App\Filament\Internet\Resources\Incidentes\Pages\CreateIncidente; use App\Filament\Internet\Resources\Incidentes\Pages\EditIncidente; use App\Filament\Internet\Resources\Incidentes\Pages\ListIncidentes;
use App\Filament\Internet\Resources\Incidentes\Schemas\IncidenteForm; use App\Filament\Internet\Resources\Incidentes\Tables\IncidentesTable; use App\Models\IncidenteInternet;
use BackedEnum; use Filament\Resources\Resource; use Filament\Schemas\Schema; use Filament\Support\Icons\Heroicon; use Filament\Tables\Table;
class IncidenteInternetResource extends Resource
{
    protected static ?string $model=IncidenteInternet::class; protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedExclamationTriangle;
    protected static ?string $navigationLabel='Falhas e incidentes'; protected static ?string $modelLabel='incidente de internet'; protected static ?string $pluralModelLabel='falhas e incidentes'; protected static ?string $slug='incidentes'; protected static ?int $navigationSort=40;
    public static function form(Schema $schema): Schema { return IncidenteForm::configure($schema); } public static function table(Table $table): Table { return IncidentesTable::configure($table); }
    public static function getPages(): array { return ['index'=>ListIncidentes::route('/'),'create'=>CreateIncidente::route('/create'),'edit'=>EditIncidente::route('/{record}/edit')]; }
}
