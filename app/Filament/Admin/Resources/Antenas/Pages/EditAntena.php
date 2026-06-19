<?php

namespace App\Filament\Admin\Resources\Antenas\Pages;

use App\Filament\Admin\Resources\Antenas\AntenaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAntena extends EditRecord
{
    protected static string $resource = AntenaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
