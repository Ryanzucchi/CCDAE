<?php

namespace App\Filament\Transito\Resources\Infracoes\Pages;

use App\Filament\Transito\Resources\Infracoes\InfracaoTransitoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInfracao extends EditRecord
{
    protected static string $resource = InfracaoTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['placa'] = filled($data['placa'] ?? null) ? strtoupper($data['placa']) : null;
        unset($data['location']);

        return $data;
    }
}
