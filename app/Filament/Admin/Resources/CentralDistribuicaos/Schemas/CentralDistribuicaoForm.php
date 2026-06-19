<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CentralDistribuicaoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados Principais')->columns(2)->schema([
                    TextInput::make('nome')->required()->label('Nome da Central'),
                    Select::make('distrito_id')
                        ->relationship('distrito', 'nome')
                        ->searchable()
                        ->preload()
                        ->label('Distrito'),
                    TextInput::make('codigo_patrimonio')->label('Código de Patrimônio'),
                    Select::make('tipo')
                        ->options([
                            'Energia' => 'Energia Elétrica',
                            'Telecom' => 'Telecomunicações / Datacenter',
                            'Agua' => 'Saneamento / Água',
                            'Gas' => 'Gás Natural',
                        ])
                        ->label('Tipo de Central'),
                    TextInput::make('capacidade')->label('Capacidade (ex: 138kV, 10Gbps)'),
                    TextInput::make('area_m2')->numeric()->label('Área (m²)'),
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
                Section::make('Fronteiras e Localização (Nó do Grafo)')->schema([
                    \Filament\Forms\Components\Hidden::make('latitude'),
                    \Filament\Forms\Components\Hidden::make('longitude'),
                    \Filament\Forms\Components\Hidden::make('geojson')->dehydrated(),
                    \Dotswan\MapPicker\Fields\Map::make('location')
                        ->label('Desenhe o polígono da Central de Distribuição ou marque o ponto')
                        ->columnSpanFull()
                        ->dehydrated(false)
                        ->defaultLocation(latitude: -23.550520, longitude: -46.633308)
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            if (isset($state['geojson']) && !empty($state['geojson'])) {
                                $geo = $state['geojson'];
                                if (isset($geo['type']) && $geo['type'] === 'FeatureCollection') {
                                    $geo = $geo['features'][0]['geometry'] ?? $geo;
                                }
                                $set('geojson', $geo);
                                try {
                                    $centroid = \Illuminate\Support\Facades\DB::selectOne(
                                        "SELECT ST_X(ST_Centroid(ST_GeomFromGeoJSON(?))) as lng, ST_Y(ST_Centroid(ST_GeomFromGeoJSON(?))) as lat", 
                                        [json_encode($geo), json_encode($geo)]
                                    );
                                    if ($centroid && $centroid->lat && $centroid->lng) {
                                        $set('latitude', round($centroid->lat, 6));
                                        $set('longitude', round($centroid->lng, 6));
                                    }
                                } catch (\Exception $e) {}
                            } elseif (isset($state['lat']) && isset($state['lng'])) {
                                $set('latitude', round($state['lat'], 7));
                                $set('longitude', round($state['lng'], 7));
                                $set('geojson', null);
                            } else {
                                $set('geojson', null);
                                $set('latitude', null);
                                $set('longitude', null);
                            }
                        })
                        ->afterStateHydrated(function ($state, $record, callable $set): void {
                            if ($record) {
                                $set('location', [
                                    'lat' => $record->latitude, 
                                    'lng' => $record->longitude,
                                    'geojson' => is_string($record->geojson) ? json_decode($record->geojson, true) : $record->geojson
                                ]);
                            }
                        })
                        ->clickable(true)
                        ->showMarker(true)
                        ->showFullscreenControl(true)
                        ->showZoomControl(true)
                        ->geoMan(true)
                        ->geoManEditable(true)
                        ->drawPolygon(true)
                        ->drawCircle(false)
                        ->drawPolyline(false)
                        ->drawRectangle(true)
                        ->editPolygon(true)
                        ->deleteLayer(true),
                ]),
                Section::make('Observações')->schema([
                    Textarea::make('observacoes')->columnSpanFull()->label('Observações Adicionais'),
                ])
            ]);
    }
}
