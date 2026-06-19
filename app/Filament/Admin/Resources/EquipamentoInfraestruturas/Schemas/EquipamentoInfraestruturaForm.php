<?php

namespace App\Filament\Admin\Resources\EquipamentoInfraestruturas\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EquipamentoInfraestruturaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados Principais')->columns(3)->schema([
                    TextInput::make('nome')->required()->label('Nome do Equipamento'),
                    TextInput::make('codigo_patrimonio')->label('Código de Patrimônio'),
                    TextInput::make('tipo')->label('Tipo (Ex: Transformador, Roteador)'),
                    TextInput::make('modelo')->label('Modelo'),
                    TextInput::make('numero_serie')->label('Número de Série'),
                ]),
                Section::make('Vínculos Espaciais')->columns(3)->schema([
                    Select::make('distrito_id')
                        ->relationship('distrito', 'nome')
                        ->searchable()
                        ->preload()
                        ->label('Distrito'),
                    Select::make('poste_id')
                        ->relationship('poste', 'codigo_patrimonio')
                        ->searchable()
                        ->preload()
                        ->label('Alocado no Poste (opcional)'),
                    Select::make('central_id')
                        ->relationship('central', 'nome')
                        ->searchable()
                        ->preload()
                        ->label('Alocado na Central (opcional)'),
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
                Section::make('Localização Física (Caso não vinculado a Poste/Central)')->schema([
                    \Filament\Forms\Components\Hidden::make('latitude'),
                    \Filament\Forms\Components\Hidden::make('longitude'),
                    \Dotswan\MapPicker\Fields\Map::make('location')
                        ->label('Clique no mapa para marcar a posição')
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
