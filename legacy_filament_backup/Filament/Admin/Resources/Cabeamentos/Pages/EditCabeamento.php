<?php

namespace App\Filament\Admin\Resources\Cabeamentos\Pages;

use App\Filament\Admin\Resources\Cabeamentos\CabeamentoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCabeamento extends EditRecord
{
    protected static string $resource = CabeamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
