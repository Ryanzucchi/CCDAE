<?php

namespace App\Filament\Admin\Resources\RegistroTransitos\Pages;

use App\Filament\Admin\Resources\RegistroTransitos\RegistroTransitoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRegistroTransito extends EditRecord
{
    protected static string $resource = RegistroTransitoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
