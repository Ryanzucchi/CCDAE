<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class PosteResource extends PanelResource
{
    protected static ?string $model = \App\Models\Poste::class;
    protected static ?string $navigationLabel = 'Postes';
    protected static ?string $navigationGroup = 'Infraestrutura';
    protected static ?string $slug = 'postes';
}
