<?php
namespace App\Filament\Internet\Resources\Incidentes\Pages; use App\Filament\Internet\Resources\Incidentes\IncidenteInternetResource; use Filament\Actions\CreateAction; use Filament\Resources\Pages\ListRecords;
class ListIncidentes extends ListRecords { protected static string $resource=IncidenteInternetResource::class; protected function getHeaderActions():array{return [CreateAction::make()->label('Registrar incidente')];} }
