<?php

namespace App\Filament\Admin\Resources\RegistroTransitos\Pages;

use App\Filament\Admin\Resources\RegistroTransitos\RegistroTransitoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRegistroTransitos extends ListRecords
{
    protected static string $resource = RegistroTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
