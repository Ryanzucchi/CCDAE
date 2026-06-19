<?php

namespace App\Filament\Admin\Resources\Antenas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class AntenaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('distrito_id')
                    ->numeric(),
                TextInput::make('codigo_patrimonio'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
                    ->numeric(),
                TextInput::make('tipo_sinal'),
                TextInput::make('frequencia_mhz')
                    ->numeric(),
                TextInput::make('alcance_metros')
                    ->numeric(),
                TextInput::make('potencia_dbm')
                    ->numeric(),
                TextInput::make('proprietario'),
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
