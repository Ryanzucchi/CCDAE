<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class InfracaoTransitoResource extends PanelResource { protected static ?string $model = \App\Models\InfracaoTransito::class; protected static ?string $navigationLabel = 'Infrações'; protected static ?string $navigationGroup = 'Trânsito'; protected static ?string $slug = 'infracoes-transito'; }
