<?php

namespace App\Filament\Transito\Pages;

use App\Models\Distrito;
use App\Models\ViaTransito;
use Filament\Pages\Page;

class MapaTransito extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Mapa de trânsito';

    protected static ?string $title = 'Mapa de trânsito';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.transito.pages.mapa-transito';

    public function getViasProperty(): array
    {
        return ViaTransito::query()->get(['id', 'nome', 'geojson', 'nivel_congestionamento', 'velocidade_media', 'impacto_manutencao', 'direcao_fluxo'])->toArray();
    }

    public function getDistritosProperty(): array
    {
        return Distrito::query()->get(['id', 'nome', 'geojson'])->toArray();
    }
}
