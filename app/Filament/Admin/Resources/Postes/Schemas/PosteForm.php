<?php

namespace App\Filament\Admin\Resources\Postes\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PosteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados Principais')->columns(2)->schema([
                    Select::make('distrito_id')
                        ->relationship('distrito', 'nome')
                        ->searchable()
                        ->preload()
                        ->label('Distrito'),
                    TextInput::make('codigo_patrimonio')->label('Código de Patrimônio'),
                    Select::make('material')
                        ->options([
                            'concreto' => 'Concreto',
                            'madeira' => 'Madeira',
                            'ferro' => 'Ferro',
                            'fibra' => 'Fibra',
                        ])
                        ->required()
                        ->default('concreto'),
                    TextInput::make('altura_metros')->numeric()->label('Altura')->suffix('m'),
                    TextInput::make('resistencia_kg')->numeric()->label('Resistência')->suffix('kg'),
                    Toggle::make('possui_iluminacao')->label('Possui Iluminação')->inline(false),
                ]),
                Section::make('Desgaste & Vida Útil')->columns(3)->schema([
                    Select::make('estado_conservacao')
                        ->options([
                            'novo' => 'Novo',
                            'bom' => 'Bom',
                            'regular' => 'Regular',
                            'ruim' => 'Ruim',
                            'critico' => 'Crítico',
                        ])
                        ->required()
                        ->default('bom')
                        ->label('Estado de Conservação'),
                    DatePicker::make('data_instalacao')->label('Instalação'),
                    DatePicker::make('ultima_manutencao')->label('Última Manutenção'),
                ]),
                Section::make('Localização Espacial (Nó do Grafo)')->schema([
                    \Filament\Forms\Components\Hidden::make('latitude'),
                    \Filament\Forms\Components\Hidden::make('longitude'),
                    \Dotswan\MapPicker\Fields\Map::make('location')
                        ->label('Clique no mapa para marcar a posição exata do Poste')
                        ->columnSpanFull()
                        ->dehydrated(false)
                        ->defaultLocation(latitude: -23.550520, longitude: -46.633308)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (isset($state['lat']) && isset($state['lng'])) {
                                $set('latitude', round($state['lat'], 7));
                                $set('longitude', round($state['lng'], 7));
                            } else {
                                $set('latitude', null);
                                $set('longitude', null);
                            }
                        })
                        ->afterStateHydrated(function ($state, $record, callable $set): void {
                            if ($record && $record->latitude && $record->longitude) {
                                $set('location', ['lat' => $record->latitude, 'lng' => $record->longitude]);
                            }
                        })
                        ->clickable(true)
                        ->showMarker(true)
                        ->showFullscreenControl(true)
                        ->showZoomControl(true)
                        ->geoMan(false),
                ]),
                Section::make('Observações')->schema([
                    Textarea::make('observacoes')->columnSpanFull()->label('Observações Adicionais'),
                ])
            ]);
    }
}
