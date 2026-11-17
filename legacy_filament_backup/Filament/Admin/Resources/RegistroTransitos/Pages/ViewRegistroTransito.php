<?php

namespace App\Filament\Admin\Resources\RegistroTransitos\Pages;

use App\Filament\Admin\Resources\RegistroTransitos\RegistroTransitoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRegistroTransito extends ViewRecord
{
    protected static string $resource = RegistroTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
