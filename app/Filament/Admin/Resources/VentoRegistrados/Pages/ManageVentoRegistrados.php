<?php

namespace App\Filament\Admin\Resources\VentoRegistrados\Pages;

use App\Filament\Admin\Resources\VentoRegistrados\VentoRegistradoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageVentoRegistrados extends ManageRecords
{
    protected static string $resource = VentoRegistradoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
