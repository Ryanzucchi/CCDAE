<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class RegistroTransitoResource extends PanelResource { protected static ?string $model = \App\Models\RegistroTransito::class; protected static ?string $navigationLabel = 'Registros de trânsito'; protected static ?string $navigationGroup = 'Trânsito'; protected static ?string $slug = 'registros-transito'; }
