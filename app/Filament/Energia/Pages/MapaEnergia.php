<?php

namespace App\Filament\Energia\Pages;

use Filament\Pages\Page;

class MapaEnergia extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationLabel = 'Mapa de energia';

    protected static ?string $title = 'Densidade da rede elétrica';

    protected static ?int $navigationSort = 20;

    protected string $view = 'filament.energia.pages.mapa-analitico';

    public string $mapType = 'densidade';
}
