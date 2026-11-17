<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class ChuvaRegistradaResource extends PanelResource { protected static ?string $model = \App\Models\ChuvaRegistrada::class; protected static ?string $navigationLabel = 'Chuvas registradas'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'chuvas-registradas'; }
