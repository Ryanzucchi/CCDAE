<?php

namespace App\Filament\Admin\Resources\Antenas;

use App\Filament\Admin\Resources\Antenas\Pages\CreateAntena;
use App\Filament\Admin\Resources\Antenas\Pages\EditAntena;
use App\Filament\Admin\Resources\Antenas\Pages\ListAntenas;
use App\Filament\Admin\Resources\Antenas\Schemas\AntenaForm;
use App\Filament\Admin\Resources\Antenas\Tables\AntenasTable;
use App\Models\Antena;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AntenaResource extends Resource
{
    protected static ?string $model = Antena::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AntenaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AntenasTable::configure($table);
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
            'index' => ListAntenas::route('/'),
            'create' => CreateAntena::route('/create'),
            'edit' => EditAntena::route('/{record}/edit'),
        ];
    }
}
