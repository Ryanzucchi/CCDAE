<?php

namespace App\Filament\Admin\Resources\ViaTransitos;

use App\Filament\Admin\Resources\ViaTransitos\Pages\CreateViaTransito;
use App\Filament\Admin\Resources\ViaTransitos\Pages\EditViaTransito;
use App\Filament\Admin\Resources\ViaTransitos\Pages\ListViaTransitos;
use App\Filament\Admin\Resources\ViaTransitos\Pages\ViewViaTransito;
use App\Filament\Admin\Resources\ViaTransitos\Schemas\ViaTransitoForm;
use App\Filament\Admin\Resources\ViaTransitos\Schemas\ViaTransitoInfolist;
use App\Filament\Admin\Resources\ViaTransitos\Tables\ViaTransitosTable;
use App\Models\ViaTransito;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ViaTransitoResource extends Resource
{
    protected static ?string $model = ViaTransito::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|\UnitEnum|null $navigationGroup = 'Trânsito e Mobilidade';

    public static function form(Schema $schema): Schema
    {
        return ViaTransitoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ViaTransitoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ViaTransitosTable::configure($table);
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
            'index' => ListViaTransitos::route('/'),
            'create' => CreateViaTransito::route('/create'),
            'view' => ViewViaTransito::route('/{record}'),
            'edit' => EditViaTransito::route('/{record}/edit'),
        ];
    }
}
