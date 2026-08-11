<?php

namespace App\Filament\Transito\Pages;

use Filament\Pages\Dashboard;

class TransitoDashboard extends Dashboard
{
    protected static ?string $title = 'Trânsito e Mobilidade';
    protected static ?string $navigationLabel = 'Visão geral';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [\App\Filament\Transito\Widgets\ResumoTransito::class];
    }
}
