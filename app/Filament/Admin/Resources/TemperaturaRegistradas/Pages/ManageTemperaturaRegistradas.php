<?php

namespace App\Filament\Admin\Resources\TemperaturaRegistradas\Pages;

use App\Filament\Admin\Resources\TemperaturaRegistradas\TemperaturaRegistradaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTemperaturaRegistradas extends ManageRecords
{
    protected static string $resource = TemperaturaRegistradaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
