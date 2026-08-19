<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\EnergiaPanelProvider;
use App\Providers\Filament\InternetPanelProvider;
use App\Providers\Filament\TransitoPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    TransitoPanelProvider::class,
    EnergiaPanelProvider::class,
    InternetPanelProvider::class,
];
