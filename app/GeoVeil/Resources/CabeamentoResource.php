<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class CabeamentoResource extends PanelResource { protected static ?string $model = \App\Models\Cabeamento::class; protected static ?string $navigationLabel = 'Cabeamentos'; protected static ?string $navigationGroup = 'Infraestrutura'; protected static ?string $slug = 'cabeamentos'; }
