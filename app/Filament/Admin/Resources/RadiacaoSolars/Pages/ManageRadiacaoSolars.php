<?php

namespace App\Filament\Admin\Resources\RadiacaoSolars\Pages;

use App\Filament\Admin\Resources\RadiacaoSolars\RadiacaoSolarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRadiacaoSolars extends ManageRecords
{
    protected static string $resource = RadiacaoSolarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
