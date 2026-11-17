<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class PressaoAtmosfericaResource extends PanelResource { protected static ?string $model = \App\Models\PressaoAtmosferica::class; protected static ?string $navigationLabel = 'Pressão atmosférica'; protected static ?string $navigationGroup = 'Clima'; protected static ?string $slug = 'pressao-atmosferica'; }
