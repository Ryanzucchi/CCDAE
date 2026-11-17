<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class IncidenteEnergiaResource extends PanelResource { protected static ?string $model = \App\Models\IncidenteEnergia::class; protected static ?string $navigationLabel = 'Incidentes de energia'; protected static ?string $navigationGroup = 'Energia'; protected static ?string $slug = 'incidentes-energia'; }
