<?php

namespace App\Filament\Transito\Pages;

use Filament\Pages\Page;

class MapaAcidentes extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationLabel = 'Mapa de acidentes';

    protected static ?string $title = 'Mapa de acidentes';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.transito.pages.mapa-analitico';

    public string $mapType = 'acidentes';

    public string $description = 'Ondas tridimensionais conforme a concentração de acidentes das últimas 24 horas.';
}
