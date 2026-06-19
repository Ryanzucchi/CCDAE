<?php

namespace App\Filament\Admin\Resources\Cabeamentos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CabeamentoForm
{
    public static function getCoords($type, $id) {
        if (!$id) return null;
        if ($type === 'poste') {
            $model = \App\Models\Poste::find($id);
            if ($model && $model->latitude && $model->longitude) {
                return [(float)$model->longitude, (float)$model->latitude];
            }
        } else if ($type === 'central') {
            $model = \App\Models\CentralDistribuicao::find($id);
            if ($model && $model->latitude && $model->longitude) {
                return [(float)$model->longitude, (float)$model->latitude];
            }
        }
        return null;
    }

    public static function updateRoute(callable $get, callable $set) {
        $origemPoste = $get('poste_origem_id');
        $destinoPoste = $get('poste_destino_id');
        $origemCentral = $get('central_origem_id');
        $destinoCentral = $get('central_destino_id');
        
        $coordOrigem = self::getCoords('poste', $origemPoste) ?? self::getCoords('central', $origemCentral);
        $coordDestino = self::getCoords('poste', $destinoPoste) ?? self::getCoords('central', $destinoCentral);
        
        if ($coordOrigem && $coordDestino) {
            $geojson = [
                'type' => 'LineString',
                'coordinates' => [ $coordOrigem, $coordDestino ]
            ];
            
            $set('geojson', $geojson);
            $set('location', [
                'lat' => $coordOrigem[1],
                'lng' => $coordOrigem[0],
                'geojson' => $geojson
            ]);
            
            try {
                $dist = \Illuminate\Support\Facades\DB::selectOne(
                    "SELECT ST_Length(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)::geography) as dist", 
                    [json_encode($geojson)]
                );
                if ($dist && $dist->dist) {
                    $set('extensao_metros', round($dist->dist, 2));
                }
            } catch (\Exception $e) {}
        }
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados Principais (Aresta do Grafo)')->columns(2)->schema([
                    TextInput::make('nome')->label('Identificação da Linha / Cabo'),
                    TextInput::make('codigo_patrimonio')->label('Código de Patrimônio'),
                    Select::make('tipo_cabo')
                        ->options([
                            'fibra_optica' => 'Fibra Óptica',
                            'cobre_telefonia' => 'Cobre (Telefonia)',
                            'eletrico_alta_tensao' => 'Elétrico (Alta Tensão)',
                            'eletrico_baixa_tensao' => 'Elétrico (Baixa Tensão)',
                        ])
                        ->label('Tipo de Cabeamento'),
                    TextInput::make('capacidade')->label('Capacidade (Ex: 144 FO, 13.8kV)'),
                    TextInput::make('revestimento')->label('Revestimento'),
                    TextInput::make('extensao_metros')->numeric()->label('Extensão Calculada (m)'),
                    Toggle::make('subterraneo')->label('Cabeamento Subterrâneo')->inline(false),
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
                Section::make('Roteamento do Grafo (Conexões Físicas)')->columns(2)->schema([
                    Select::make('poste_origem_id')
                        ->relationship('posteOrigem', 'codigo_patrimonio')
                        ->searchable()
                        ->label('Poste de Origem')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateRoute($get, $set)),
                    Select::make('poste_destino_id')
                        ->relationship('posteDestino', 'codigo_patrimonio')
                        ->searchable()
                        ->label('Poste de Destino')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateRoute($get, $set)),
                    Select::make('central_origem_id')
                        ->relationship('centralOrigem', 'nome')
                        ->searchable()
                        ->label('Central de Origem')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateRoute($get, $set)),
                    Select::make('central_destino_id')
                        ->relationship('centralDestino', 'nome')
                        ->searchable()
                        ->label('Central de Destino')
                        ->live()
                        ->afterStateUpdated(fn ($state, callable $set, callable $get) => self::updateRoute($get, $set)),
                    Select::make('distrito_id')
                        ->relationship('distrito', 'nome')
                        ->searchable()
                        ->preload()
                        ->label('Distrito Predominante')
                        ->columnSpanFull(),
                ]),
                Section::make('Traçado Espacial (Edge)')->schema([
                    \Filament\Forms\Components\Hidden::make('geojson')->dehydrated(),
                    \Dotswan\MapPicker\Fields\Map::make('location')
                        ->label('O traçado é construído automaticamente quando os nós de origem e destino são conectados')
                        ->columnSpanFull()
                        ->dehydrated(false)
                        ->defaultLocation(latitude: -23.550520, longitude: -46.633308)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (isset($state['geojson']) && !empty($state['geojson'])) {
                                $geo = $state['geojson'];
                                if (isset($geo['type']) && $geo['type'] === 'FeatureCollection') {
                                    $geo = $geo['features'][0]['geometry'] ?? $geo;
                                }
                                $set('geojson', $geo);
                            } else {
                                $set('geojson', null);
                            }
                        })
                        ->afterStateHydrated(function ($state, $record, callable $set): void {
                            if ($record && $record->geojson) {
                                $set('location', [
                                    'lat' => -23.550520, // Default fallback
                                    'lng' => -46.633308,
                                    'geojson' => is_string($record->geojson) ? json_decode($record->geojson, true) : $record->geojson
                                ]);
                            }
                        })
                        ->clickable(false)
                        ->showMarker(false)
                        ->showFullscreenControl(true)
                        ->showZoomControl(true)
                        ->geoMan(true)
                        ->geoManEditable(true)
                        ->drawPolygon(false)
                        ->drawCircle(false)
                        ->drawPolyline(true)
                        ->drawRectangle(false)
                        ->editPolygon(true)
                        ->deleteLayer(true),
                ]),
                Section::make('Observações')->schema([
                    Textarea::make('observacoes')->columnSpanFull()->label('Observações Adicionais'),
                ])
            ]);
    }
}
