<?php

namespace App\Filament\Admin\Resources\EquipamentoInfraestruturas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class EquipamentoInfraestruturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('distrito_id')
                    ->numeric(),
                TextInput::make('poste_id')
                    ->numeric(),
                TextInput::make('central_id')
                    ->numeric(),
                TextInput::make('nome')
                    ->required(),
                TextInput::make('codigo_patrimonio'),
                TextInput::make('tipo'),
                TextInput::make('modelo'),
                TextInput::make('numero_serie'),
                TextInput::make('latitude')
                    ->numeric(),
                TextInput::make('longitude')
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
