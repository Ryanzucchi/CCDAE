<?php

namespace App\Filament\Transito\Pages;

use Filament\Pages\Page;

class MapaInfracoes extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Mapa de infrações';

    protected static ?string $title = 'Mapa de infrações';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.transito.pages.mapa-analitico';

    public string $mapType = 'infracoes';

    public string $description = 'Distribuição espacial das infrações registradas nos últimos 30 dias.';
}
