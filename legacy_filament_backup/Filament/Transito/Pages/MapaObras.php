<?php

namespace App\Filament\Transito\Pages;

use Filament\Pages\Page;

class MapaObras extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Mapa de obras';

    protected static ?string $title = 'Mapa de obras';

    protected static ?int $navigationSort = 60;

    protected string $view = 'filament.transito.pages.mapa-analitico';

    public string $mapType = 'obras';

    public string $description = 'Ondas tridimensionais conforme a concentração de obras ativas nas últimas 24 horas.';
}
