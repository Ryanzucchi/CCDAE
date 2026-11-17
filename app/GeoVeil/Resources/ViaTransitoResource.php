<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class ViaTransitoResource extends PanelResource
{
    protected static ?string $model = \App\Models\ViaTransito::class;
    protected static ?string $navigationLabel = 'Vias de trânsito';
    protected static ?string $navigationGroup = 'Trânsito';
    protected static ?string $slug = 'vias-transito';
}
