<?php

namespace App\Filament\Admin\Resources\EquipamentoInfraestruturas\Pages;

use App\Filament\Admin\Resources\EquipamentoInfraestruturas\EquipamentoInfraestruturaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipamentoInfraestruturas extends ListRecords
{
    protected static string $resource = EquipamentoInfraestruturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
