<?php

namespace App\Filament\Admin\Resources\Antenas\Pages;

use App\Filament\Admin\Resources\Antenas\AntenaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAntenas extends ListRecords
{
    protected static string $resource = AntenaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
