<?php
namespace App\GeoVeil\Resources;
use GeoVeil\PanelResource;
final class CentralDistribuicaoResource extends PanelResource { protected static ?string $model = \App\Models\CentralDistribuicao::class; protected static ?string $navigationLabel = 'Centrais de distribuição'; protected static ?string $navigationGroup = 'Infraestrutura'; protected static ?string $slug = 'centrais-distribuicao'; }
