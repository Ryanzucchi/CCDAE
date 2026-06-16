<?php

namespace App\Filament\Admin\Resources\Distritos;

use App\Filament\Admin\Resources\Distritos\Pages\ManageDistritos;
use App\Models\Distrito;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DistritoResource extends Resource
{
    protected static ?string $model = Distrito::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Grid::make(2)->components([
                    TextInput::make('nome')
                        ->required()
                        ->label('Nome do Distrito'),
                    TextInput::make('cidade')
                        ->required()
                        ->label('Cidade')
                        ->live(debounce: 1000)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if ($state) {
                                try {
                                    $response = \Illuminate\Support\Facades\Http::withHeaders([
                                        'User-Agent' => 'CCDAE-System'
                                    ])->get('https://nominatim.openstreetmap.org/search', [
                                        'q' => $state . ', MT',
                                        'format' => 'json',
                                        'limit' => 1
                                    ]);
                                    if ($response->successful() && !empty($response->json())) {
                                        $data = $response->json()[0];
                                        $loc = $get('location') ?? [];
                                        $loc['lat'] = (float)$data['lat'];
                                        $loc['lng'] = (float)$data['lon'];
                                        $set('location', $loc);
                                    }
                                } catch (\Exception $e) {}
                            }
                        }),
                    TextInput::make('latitude')
                        ->numeric()
                        ->readOnly()
                        ->helperText('Preenchido automaticamente ao clicar no mapa.'),
                    TextInput::make('longitude')
                        ->numeric()
                        ->readOnly(),
                ]),
                \Dotswan\MapPicker\Fields\Map::make('location')
                    ->label('Fronteiras e Localização no Mapa')
                    ->columnSpanFull()
                    ->defaultLocation(latitude: -23.550520, longitude: -46.633308)
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        $set('latitude', $state['lat']);
                        $set('longitude', $state['lng']);
                        
                        // Extrai o geojson desenhado na tela para a coluna do banco
                        if (isset($state['geojson'])) {
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
                        if ($record) {
                            $set('location', [
                                'lat' => $record->latitude, 
                                'lng' => $record->longitude,
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
                    ->geoManPosition('topleft')
                    ->drawPolygon(true)
                    ->drawCircle(false)
                    ->drawPolyline(false)
                    ->drawRectangle(true)
                    ->drawCircleMarker(false)
                    ->drawText(false)
                    ->editPolygon(true)
                    ->deleteLayer(true),
                \Filament\Forms\Components\ViewField::make('map_injector')
                    ->view('filament.admin.views.map-districts-injector')
                    ->hiddenLabel()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->searchable(),
                TextColumn::make('latitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('longitude')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cidade')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(function (array $data, $record): array {
                        $data['geojson'] = Distrito::autoShrinkGeojson($data['geojson'] ?? null, $record->id);
                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDistritos::route('/'),
        ];
    }
}
