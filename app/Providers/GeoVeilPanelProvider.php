<?php

namespace App\Providers;

use GeoVeil\Panel;
use GeoVeil\PanelProvider;

class GeoVeilPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->default()->id('admin')->path('admin')
            ->discoverResources(app_path('GeoVeil/Resources'), 'App\\GeoVeil\\Resources')
            ->discoverPages(app_path('GeoVeil/Pages'), 'App\\GeoVeil\\Pages')
            ->discoverWidgets(app_path('GeoVeil/Widgets'), 'App\\GeoVeil\\Widgets');
    }
}
