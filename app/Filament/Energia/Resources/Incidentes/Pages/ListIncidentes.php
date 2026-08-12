<?php
namespace App\Filament\Energia\Resources\Incidentes\Pages;
use App\Filament\Energia\Resources\Incidentes\IncidenteEnergiaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListIncidentes extends ListRecords { protected static string $resource=IncidenteEnergiaResource::class; protected function getHeaderActions(): array { return [CreateAction::make()->label('Registrar incidente')]; } }
