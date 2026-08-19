<?php

namespace App\Filament\Energia\Resources\Incidentes\Pages;

use App\Filament\Energia\Resources\Incidentes\IncidenteEnergiaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncidente extends CreateRecord
{
    protected static string $resource = IncidenteEnergiaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por'] = auth()->id();
        unset($data['location']);

        return $data;
    }
}
