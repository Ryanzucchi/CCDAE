<?php

namespace App\Filament\Admin\Resources\ChuvaRegistradas\Pages;

use App\Filament\Admin\Resources\ChuvaRegistradas\ChuvaRegistradaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageChuvaRegistradas extends ManageRecords
{
    protected static string $resource = ChuvaRegistradaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
