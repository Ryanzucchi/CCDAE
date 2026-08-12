<?php
namespace App\Filament\Energia\Pages;
use Filament\Pages\Page;
class MapaConsumo extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Mapa de consumo';
    protected static ?string $title = 'Consumo médio estimado';
    protected static ?int $navigationSort = 30;
    protected string $view = 'filament.energia.pages.mapa-analitico';
    public string $mapType = 'consumo';
}
