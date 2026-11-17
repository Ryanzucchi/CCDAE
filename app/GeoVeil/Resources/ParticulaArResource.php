<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class ParticulaArResource extends PanelResource { protected static ?string $model = \App\Models\ParticulaAr::class; protected static ?string $navigationLabel = 'Partículas do ar'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'particulas-ar'; }
