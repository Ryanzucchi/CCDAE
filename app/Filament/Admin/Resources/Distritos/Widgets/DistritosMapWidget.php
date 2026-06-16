<?php

namespace App\Filament\Admin\Resources\Distritos\Widgets;

use App\Models\Distrito;
use Filament\Widgets\Widget;

class DistritosMapWidget extends Widget
{
    protected string $view = 'filament.admin.widgets.distritos-map-widget';

    protected int | string | array $columnSpan = 'full';

    public function getDistritos(): array
    {
        return Distrito::get(['id', 'nome', 'cidade', 'latitude', 'longitude', 'geojson'])
            ->toArray();
    }
}
