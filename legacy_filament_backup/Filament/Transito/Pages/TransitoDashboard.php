<?php

namespace App\Filament\Transito\Pages;

use App\Filament\Transito\Widgets\ResumoTransito;
use Filament\Pages\Dashboard;

class TransitoDashboard extends Dashboard
{
    protected static ?string $title = 'Trânsito e Mobilidade';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [ResumoTransito::class];
    }
}
