<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class VentoRegistradoResource extends PanelResource { protected static ?string $model = \App\Models\VentoRegistrado::class; protected static ?string $navigationLabel = 'Ventos'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'ventos-registrados'; }
