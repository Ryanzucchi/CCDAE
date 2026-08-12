<?php
namespace App\Filament\Energia\Resources\Incidentes\Pages;
use App\Filament\Energia\Resources\Incidentes\IncidenteEnergiaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditIncidente extends EditRecord { protected static string $resource=IncidenteEnergiaResource::class; protected function mutateFormDataBeforeSave(array $data): array { unset($data['location']); return $data; } protected function getHeaderActions(): array { return [DeleteAction::make()]; } }
