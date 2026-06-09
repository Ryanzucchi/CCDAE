<?php

namespace App\Filament\Admin\Resources\ParticulaArs\Pages;

use App\Filament\Admin\Resources\ParticulaArs\ParticulaArResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageParticulaArs extends ManageRecords
{
    protected static string $resource = ParticulaArResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
