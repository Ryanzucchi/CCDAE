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
                TextInput::make('osm_way_id')
                    ->label('ID da via no OpenStreetMap')
                    ->disabled(),
                Select::make('tipo')
                    ->options([
                        'trunk' => 'Rodovia / via expressa',
                        'primary' => 'Via primária',
                        'secondary' => 'Via secundária',
                        'tertiary' => 'Via terciária',
                        'residential' => 'Via residencial',
                    ]),
                TextInput::make('numero_faixas')
                    ->label('Número de faixas')
                    ->numeric()
                    ->minValue(1),
                Select::make('sentido')
                    ->options(['unica' => 'Mão única', 'dupla' => 'Mão dupla']),
                Select::make('direcao_fluxo')
                    ->label('Direção do fluxo')
                    ->options([
                        'inicio_fim' => 'Início → fim da geometria',
                        'fim_inicio' => 'Fim → início da geometria',
                        'ambos' => 'Ambos os sentidos',
                    ])
                    ->default('ambos')
                    ->required(),
                TextInput::make('limite_velocidade')
                    ->label('Limite de velocidade (km/h)')
                    ->numeric()
                    ->minValue(1),
                Select::make('estado_pavimento')
                    ->label('Pavimento / conservação')
                    ->options([
                        'bom' => 'Bom',
                        'regular' => 'Regular',
                        'ruim' => 'Ruim',
                        'asfalto' => 'Asfalto',
                        'concreto' => 'Concreto',
                        'paver' => 'Paver / paralelepípedo',
                        'cascalho' => 'Cascalho',
                        'terra' => 'Terra',
                        'desconhecido' => 'Desconhecido',
                    ]),
                TextInput::make('inclinacao_pista')
                    ->label('Inclinação da pista (%)')
                    ->numeric(),
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
