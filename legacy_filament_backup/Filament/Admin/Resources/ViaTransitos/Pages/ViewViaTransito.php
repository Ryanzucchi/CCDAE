<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Pages;

use App\Filament\Admin\Resources\ViaTransitos\ViaTransitoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewViaTransito extends ViewRecord
{
    protected static string $resource = ViaTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
