<?php

namespace App\Filament\Admin\Resources\ViaTransitos\Pages;

use App\Filament\Admin\Resources\ViaTransitos\ViaTransitoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditViaTransito extends EditRecord
{
    protected static string $resource = ViaTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
