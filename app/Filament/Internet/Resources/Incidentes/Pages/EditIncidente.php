<?php

namespace App\Filament\Internet\Resources\Incidentes\Pages;

use App\Filament\Internet\Resources\Incidentes\IncidenteInternetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIncidente extends EditRecord
{
    protected static string $resource = IncidenteInternetResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['location']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
