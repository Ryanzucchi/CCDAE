<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos\Pages;

use App\Filament\Admin\Resources\CentralDistribuicaos\CentralDistribuicaoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCentralDistribuicaos extends ListRecords
{
    protected static string $resource = CentralDistribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
