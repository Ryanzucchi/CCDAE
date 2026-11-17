<?php

namespace App\Filament\Internet\Resources\Incidentes\Pages;

use App\Filament\Internet\Resources\Incidentes\IncidenteInternetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncidente extends CreateRecord
{
    protected static string $resource = IncidenteInternetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por'] = auth()->id();
        unset($data['location']);

        return $data;
    }
}
