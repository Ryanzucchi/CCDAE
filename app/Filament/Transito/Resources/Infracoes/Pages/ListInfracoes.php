<?php

namespace App\Filament\Transito\Resources\Infracoes\Pages;

use App\Filament\Transito\Resources\Infracoes\InfracaoTransitoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInfracoes extends ListRecords
{
    protected static string $resource = InfracaoTransitoResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()->label('Registrar infração')]; }
}
