<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class RadiacaoSolarResource extends PanelResource { protected static ?string $model = \App\Models\RadiacaoSolar::class; protected static ?string $navigationLabel = 'Radiação solar'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'radiacao-solar'; }
