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
                        ->label('Cidade'),
                    TextInput::make('latitude')
                        ->required()
                        ->numeric()
                        ->readOnly()
                        ->helperText('Preenchido automaticamente ao clicar no mapa.'),
                    TextInput::make('longitude')
                        ->required()
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
                            $set('geojson', $state['geojson']);
                        } else {
                            $set('geojson', null);
                        }
                    })
                    ->afterStateHydrated(function ($state, $record, \Filament\Forms\Set $set): void {
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
                    ->geoManPosition('topleft')
                    ->drawPolygon(true)
                    ->drawCircle(false)
                    ->drawPolyline(false)
                    ->drawRectangle(true)
                    ->drawCircleMarker(false)
                    ->drawText(false)
                    ->editPolygon(true)
                    ->deleteLayer(true),
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
                EditAction::make(),
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
