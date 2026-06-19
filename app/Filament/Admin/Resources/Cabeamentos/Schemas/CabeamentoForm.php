<?php

namespace App\Filament\Admin\Resources\Cabeamentos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CabeamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('distrito_id')
                    ->numeric(),
                TextInput::make('nome'),
                TextInput::make('codigo_patrimonio'),
                TextInput::make('tipo_cabo'),
                TextInput::make('capacidade'),
                TextInput::make('revestimento'),
                Toggle::make('subterraneo')
                    ->required(),
                TextInput::make('extensao_metros')
                    ->numeric(),
                TextInput::make('geojson'),
                TextInput::make('poste_origem_id')
                    ->numeric(),
                TextInput::make('poste_destino_id')
                    ->numeric(),
                TextInput::make('central_origem_id')
                    ->numeric(),
                TextInput::make('central_destino_id')
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
