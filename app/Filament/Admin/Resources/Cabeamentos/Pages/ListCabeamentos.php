<?php

namespace App\Filament\Admin\Resources\Cabeamentos\Pages;

use App\Filament\Admin\Resources\Cabeamentos\CabeamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCabeamentos extends ListRecords
{
    protected static string $resource = CabeamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
