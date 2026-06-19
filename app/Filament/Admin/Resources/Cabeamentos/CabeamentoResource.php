<?php

namespace App\Filament\Admin\Resources\Cabeamentos;

use App\Filament\Admin\Resources\Cabeamentos\Pages\CreateCabeamento;
use App\Filament\Admin\Resources\Cabeamentos\Pages\EditCabeamento;
use App\Filament\Admin\Resources\Cabeamentos\Pages\ListCabeamentos;
use App\Filament\Admin\Resources\Cabeamentos\Schemas\CabeamentoForm;
use App\Filament\Admin\Resources\Cabeamentos\Tables\CabeamentosTable;
use App\Models\Cabeamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CabeamentoResource extends Resource
{
    protected static ?string $model = Cabeamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CabeamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CabeamentosTable::configure($table);
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
            'index' => ListCabeamentos::route('/'),
            'create' => CreateCabeamento::route('/create'),
            'edit' => EditCabeamento::route('/{record}/edit'),
        ];
    }
}
