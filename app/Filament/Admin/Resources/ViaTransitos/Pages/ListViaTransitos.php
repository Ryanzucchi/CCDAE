<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Pages;

use App\Filament\Admin\Resources\ViaTransitos\ViaTransitoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListViaTransitos extends ListRecords
{
    protected static string $resource = ViaTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
