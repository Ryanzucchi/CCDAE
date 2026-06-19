<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ViaTransitoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('distrito_id')
                    ->relationship('distrito', 'id'),
                TextInput::make('nome'),
                TextInput::make('geojson'),
                TextInput::make('nivel_congestionamento')
                    ->required()
                    ->default('livre'),
                TextInput::make('velocidade_media')
                    ->numeric(),
                TextInput::make('volume_veiculos')
                    ->numeric(),
                TextInput::make('impacto_manutencao')
                    ->required()
                    ->default('baixo'),
                DateTimePicker::make('ultima_atualizacao')
                    ->required(),
            ]);
    }
}
