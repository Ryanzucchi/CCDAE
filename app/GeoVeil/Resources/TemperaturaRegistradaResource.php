<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class TemperaturaRegistradaResource extends PanelResource { protected static ?string $model = \App\Models\TemperaturaRegistrada::class; protected static ?string $navigationLabel = 'Temperaturas'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'temperaturas-registradas'; }
