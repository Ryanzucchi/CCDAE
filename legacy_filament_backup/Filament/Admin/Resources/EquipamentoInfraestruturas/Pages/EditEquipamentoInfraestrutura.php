<?php

namespace App\Filament\Admin\Resources\EquipamentoInfraestruturas\Pages;

use App\Filament\Admin\Resources\EquipamentoInfraestruturas\EquipamentoInfraestruturaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEquipamentoInfraestrutura extends EditRecord
{
    protected static string $resource = EquipamentoInfraestruturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
