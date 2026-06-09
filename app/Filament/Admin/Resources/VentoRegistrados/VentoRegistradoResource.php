<?php
namespace App\Filament\Admin\Resources\VentoRegistrados;
use App\Filament\Admin\Resources\VentoRegistrados\Pages\ManageVentoRegistrados;
use App\Models\VentoRegistrado;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class VentoRegistradoResource extends Resource {
    protected static ?string $model = VentoRegistrado::class;
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedVariable;
    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';
    protected static ?string $modelLabel = 'Vento';
    public static function table(Table $table): Table {
        return $table->recordAction(null)->columns([
            TextColumn::make('distrito.nome')->label('Distrito')->sortable()->searchable(),
            TextColumn::make('timestamp')->label('Data/Hora')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('velocidade')->label('Velocidade (km/h)')->sortable(),
            TextColumn::make('direcao')->label('Direção (°)')->sortable(),
        ])->defaultKeySort(false)->defaultSort('timestamp', 'desc');
    }
    public static function getPages(): array { return ['index' => ManageVentoRegistrados::route('/')]; }
}
