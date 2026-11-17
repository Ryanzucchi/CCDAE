<?php

namespace App\Filament\Transito\Pages;

use Filament\Pages\Page;

class MapaVolumeVeiculos extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Volume de veículos';

    protected static ?string $title = 'Mapa de volume de veículos';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.transito.pages.mapa-analitico';

    public string $mapType = 'volume';

    public string $description = 'Ondas tridimensionais proporcionais ao volume médio das últimas 24 horas.';
}
