<?php
namespace App\Filament\Internet\Pages;
use Filament\Pages\Page;
class MapaRede extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal'; protected static ?string $navigationLabel = 'Mapa da rede';
    protected static ?string $title = 'Densidade da rede de internet'; protected static ?int $navigationSort = 20;
    protected string $view = 'filament.internet.pages.mapa-analitico'; public string $mapType = 'densidade_rede';
}
