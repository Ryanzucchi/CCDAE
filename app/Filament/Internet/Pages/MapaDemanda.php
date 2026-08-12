<?php
namespace App\Filament\Internet\Pages;
use Filament\Pages\Page;
class MapaDemanda extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar'; protected static ?string $navigationLabel = 'Mapa de demanda';
    protected static ?string $title = 'Demanda potencial de internet'; protected static ?int $navigationSort = 30;
    protected string $view = 'filament.internet.pages.mapa-analitico'; public string $mapType = 'demanda';
}
