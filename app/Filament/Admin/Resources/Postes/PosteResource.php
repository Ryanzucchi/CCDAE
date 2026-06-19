<?php

namespace App\Filament\Admin\Resources\Postes;

use App\Filament\Admin\Resources\Postes\Pages\CreatePoste;
use App\Filament\Admin\Resources\Postes\Pages\EditPoste;
use App\Filament\Admin\Resources\Postes\Pages\ListPostes;
use App\Filament\Admin\Resources\Postes\Schemas\PosteForm;
use App\Filament\Admin\Resources\Postes\Tables\PostesTable;
use App\Models\Poste;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PosteResource extends Resource
{
    protected static ?string $model = Poste::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Infraestrutura Urbana';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    public static function form(Schema $schema): Schema
    {
        return PosteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PostesTable::configure($table);
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
            'index' => ListPostes::route('/'),
            'create' => CreatePoste::route('/create'),
            'edit' => EditPoste::route('/{record}/edit'),
        ];
    }
}
