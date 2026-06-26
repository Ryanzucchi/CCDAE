<?php

namespace App\Filament\Admin\Resources\RegistroTransitos\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RegistroTransitoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('via_transito_id')
                    ->relationship('viaTransito', 'nome')
                    ->required(),
                DateTimePicker::make('timestamp')
                    ->required(),
                TextInput::make('veiculos_total')
                    ->numeric(),
                TextInput::make('velocidade_media')
                    ->numeric(),
                TextInput::make('altura_media_veiculos')
                    ->label('Altura Média')
                    ->numeric()
                    ->suffix('m'),
                TextInput::make('altura_maxima_veiculos')
                    ->label('Altura Máxima')
                    ->numeric()
                    ->suffix('m'),
                TextInput::make('indice_congestionamento')
                    ->numeric(),
                TextInput::make('nivel_servico')
                    ->maxLength(255),
            ]);
    }
}
