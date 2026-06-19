<?php

namespace App\Filament\Admin\Resources\Postes\Pages;

use App\Filament\Admin\Resources\Postes\PosteResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPoste extends EditRecord
{
    protected static string $resource = PosteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
