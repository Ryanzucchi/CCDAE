<?php

namespace App\Filament\Admin\Resources\IndiceUVS\Pages;

use App\Filament\Admin\Resources\IndiceUVS\IndiceUVResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIndiceUVS extends ManageRecords
{
    protected static string $resource = IndiceUVResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
