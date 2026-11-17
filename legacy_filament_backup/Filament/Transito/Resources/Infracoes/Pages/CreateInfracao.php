<?php

namespace App\Filament\Transito\Resources\Infracoes\Pages;

use App\Filament\Transito\Resources\Infracoes\InfracaoTransitoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInfracao extends CreateRecord
{
    protected static string $resource = InfracaoTransitoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['registrado_por'] = auth()->id();
        $data['placa'] = filled($data['placa'] ?? null) ? strtoupper($data['placa']) : null;
        unset($data['location']);

        return $data;
    }
}
