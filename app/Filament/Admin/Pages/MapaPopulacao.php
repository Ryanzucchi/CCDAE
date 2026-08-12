<?php
namespace App\Filament\Admin\Pages;
use Filament\Pages\Page;
class MapaPopulacao extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Mapa de população';
    protected static ?string $title = 'Distribuição populacional';
    protected static ?string $slug = 'mapa-populacao';
    protected string $view = 'filament.admin.pages.mapa-populacao';
}
