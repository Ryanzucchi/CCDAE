<?php

namespace App\Filament\Admin\Resources\CentralDistribuicaos\Pages;

use App\Filament\Admin\Resources\CentralDistribuicaos\CentralDistribuicaoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCentralDistribuicao extends EditRecord
{
    protected static string $resource = CentralDistribuicaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
