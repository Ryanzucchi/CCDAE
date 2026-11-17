<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class AntenaResource extends PanelResource
{
    protected static ?string $model = \App\Models\Antena::class;
    protected static ?string $navigationLabel = 'Antenas';
    protected static ?string $navigationGroup = 'Internet';
    protected static ?string $slug = 'antenas';
}
