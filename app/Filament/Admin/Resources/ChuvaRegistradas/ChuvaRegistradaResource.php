<?php
namespace App\Filament\Admin\Resources\ChuvaRegistradas;
use App\Filament\Admin\Resources\ChuvaRegistradas\Pages\ManageChuvaRegistradas;
use App\Models\ChuvaRegistrada;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use BackedEnum;
use UnitEnum;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ChuvaRegistradaResource extends Resource {
    protected static ?string $model = ChuvaRegistrada::class;
    protected static BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCloudArrowDown;
    protected static UnitEnum|string|null $navigationGroup = 'Dados Climáticos';
    protected static ?string $modelLabel = 'Chuva';
    public static function table(Table $table): Table {
        return $table->recordAction(null)->columns([
            TextColumn::make('distrito.nome')->label('Distrito')->sortable()->searchable(),
            TextColumn::make('timestamp')->label('Data/Hora')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('precipitacao_mm')->label('Precipitação (mm)')->sortable(),
        ])->defaultKeySort(false)->defaultSort('timestamp', 'desc');
    }
    public static function getPages(): array { return ['index' => ManageChuvaRegistradas::route('/')]; }
}
