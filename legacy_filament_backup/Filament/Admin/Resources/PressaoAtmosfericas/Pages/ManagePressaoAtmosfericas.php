<?php

namespace App\Filament\Admin\Resources\PressaoAtmosfericas\Pages;

use App\Filament\Admin\Resources\PressaoAtmosfericas\PressaoAtmosfericaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePressaoAtmosfericas extends ManageRecords
{
    protected static string $resource = PressaoAtmosfericaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
