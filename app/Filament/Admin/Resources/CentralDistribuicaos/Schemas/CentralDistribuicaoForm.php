<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CentralDistribuicaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('distrito_id')
                    ->numeric(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('codigo_patrimonio'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('geojson'),
                TextInput::make('tipo'),
                TextInput::make('capacidade'),
                TextInput::make('area_m2')
                    ->numeric(),
                DatePicker::make('data_instalacao'),
                DatePicker::make('ultima_manutencao'),
                TextInput::make('estado_conservacao')
                    ->required()
                    ->default('bom'),
                Textarea::make('observacoes')
                    ->columnSpanFull(),
            ]);
    }
}
