<?php

namespace App\Filament\Admin\Resources\Postes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PosteForm
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
                TextInput::make('material')
                    ->required()
                    ->default('concreto'),
                TextInput::make('altura_metros')
                    ->numeric(),
                TextInput::make('resistencia_kg')
                    ->numeric(),
                Toggle::make('possui_iluminacao')
                    ->required(),
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
