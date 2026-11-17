<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class DistritoResource extends PanelResource
{
    protected static ?string $model = \App\Models\Distrito::class;
    protected static ?string $navigationLabel = 'Distritos';
    protected static ?string $navigationGroup = 'Infraestrutura';
    protected static ?string $slug = 'distritos';
    public static function fields(): array { return ['nome'=>['type'=>'text','label'=>'Nome'],'cidade'=>['type'=>'text','label'=>'Cidade'],'populacao'=>['type'=>'number','label'=>'População'],'latitude'=>['type'=>'number','label'=>'Latitude'],'longitude'=>['type'=>'number','label'=>'Longitude'],'geojson'=>['type'=>'json','label'=>'GeoJSON']]; }
}
