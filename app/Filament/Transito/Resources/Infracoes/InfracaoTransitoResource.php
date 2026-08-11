<?php

namespace App\Filament\Transito\Resources\Infracoes;

use App\Filament\Transito\Resources\Infracoes\Pages\CreateInfracao;
use App\Filament\Transito\Resources\Infracoes\Pages\EditInfracao;
use App\Filament\Transito\Resources\Infracoes\Pages\ListInfracoes;
use App\Filament\Transito\Resources\Infracoes\Schemas\InfracaoForm;
use App\Filament\Transito\Resources\Infracoes\Tables\InfracoesTable;
use App\Models\InfracaoTransito;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InfracaoTransitoResource extends Resource
{
    protected static ?string $model = InfracaoTransito::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'Infrações de trânsito';
    protected static ?string $modelLabel = 'infração';
    protected static ?string $pluralModelLabel = 'infrações de trânsito';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return InfracaoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InfracoesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInfracoes::route('/'),
            'create' => CreateInfracao::route('/create'),
            'edit' => EditInfracao::route('/{record}/edit'),
        ];
    }
}
