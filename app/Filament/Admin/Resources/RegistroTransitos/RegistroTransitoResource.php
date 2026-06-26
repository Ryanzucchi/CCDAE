<?php

namespace App\Filament\Admin\Resources\RegistroTransitos;

use App\Filament\Admin\Resources\RegistroTransitos\Pages\CreateRegistroTransito;
use App\Filament\Admin\Resources\RegistroTransitos\Pages\EditRegistroTransito;
use App\Filament\Admin\Resources\RegistroTransitos\Pages\ListRegistroTransitos;
use App\Filament\Admin\Resources\RegistroTransitos\Pages\ViewRegistroTransito;
use App\Filament\Admin\Resources\RegistroTransitos\Schemas\RegistroTransitoForm;
use App\Filament\Admin\Resources\RegistroTransitos\Schemas\RegistroTransitoInfolist;
use App\Filament\Admin\Resources\RegistroTransitos\Tables\RegistroTransitosTable;
use App\Models\RegistroTransito;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RegistroTransitoResource extends Resource
{
    protected static ?string $model = RegistroTransito::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string|\UnitEnum|null $navigationGroup = 'Trânsito e Mobilidade';

    public static function form(Schema $schema): Schema
    {
        return RegistroTransitoForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RegistroTransitoInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RegistroTransitosTable::configure($table);
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
            'index' => ListRegistroTransitos::route('/'),
            'create' => CreateRegistroTransito::route('/create'),
            'view' => ViewRegistroTransito::route('/{record}'),
            'edit' => EditRegistroTransito::route('/{record}/edit'),
        ];
    }
}
