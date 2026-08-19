<?php

namespace App\Services\Traffic;

use App\Models\InfracaoTransito;
use App\Models\ViaTransito;
use App\Services\Geo\FeatureCollectionFactory;
use App\Services\Geo\LineSampler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrafficIntensityService
{
    public function __construct(
        protected LineSampler $lineSampler,
        protected FeatureCollectionFactory $featureFactory,
    ) {}

    public function calculate(Carbon $periodStart, Carbon $periodEnd, ?string $requestedType = null): array
    {
        $vias = ViaTransito::query()->get(['id', 'nome', 'geojson'])->keyBy('id');
        $records = DB::table('registro_transitos')
            ->whereBetween('timestamp', [$periodStart, $periodEnd])
            ->get(['via_transito_id', 'veiculos_total', 'acidentes_ativos', 'obras_ativas'])
            ->groupBy('via_transito_id');

        $series = ['volume' => [], 'acidentes' => [], 'obras' => []];
        foreach ($records as $viaId => $rows) {
            $via = $vias->get($viaId);
            $samples = $this->geometrySamples($via?->geojson, 180);
            if (! $samples) {
                continue;
            }
            $properties = ['viaId' => (int) $viaId, 'name' => $via?->nome ?: 'Via'];
            foreach ($samples as $sampleIndex => $coordinates) {
                $sampleProperties = [...$properties, 'sample' => $sampleIndex + 1, 'samples' => count($samples)];
                $series['volume'][] = $this->pointFeature($coordinates, $sampleProperties, (float) $rows->avg('veiculos_total'));
                $series['acidentes'][] = $this->pointFeature($coordinates, $sampleProperties, (float) $rows->sum('acidentes_ativos'));
                $series['obras'][] = $this->pointFeature($coordinates, $sampleProperties, (float) $rows->sum('obras_ativas'));
            }
        }

        $series['volume'] = $this->aggregateSpatially($series['volume'], 0.0014, true);
        $series['acidentes'] = $this->aggregateSpatially($series['acidentes'], 0.0014, true);
        $series['obras'] = $this->aggregateSpatially($series['obras'], 0.0014, true);

        $series['infracoes'] = InfracaoTransito::query()
            ->whereBetween('ocorrido_em', [$periodStart, $periodEnd])
            ->get(['id', 'tipo', 'latitude', 'longitude', 'ocorrido_em'])
            ->map(fn (InfracaoTransito $item) => $this->pointFeature(
                [(float) $item->longitude, (float) $item->latitude],
                ['recordId' => $item->id, 'name' => $item->tipo, 'occurredAt' => $item->ocorrido_em?->format('d/m/Y H:i')],
                1,
            ))->all();
        $series['infracoes'] = $this->aggregateSpatially($series['infracoes'], 0.003);

        foreach ($series as $key => $features) {
            $values = array_map(fn ($feature) => (float) $feature['properties']['value'], $features);
            $minimum = min($values ?: [0]);
            $maximum = max($values ?: [0]);
            $range = $maximum - $minimum;
            $series[$key] = [
                'type' => 'FeatureCollection',
                'properties' => [
                    'minimum' => $minimum,
                    'maximum' => $maximum,
                    'range' => $range,
                    'scale' => 'city-min-max',
                    'periodStart' => $periodStart->toIso8601String(),
                    'periodEnd' => $periodEnd->toIso8601String(),
                ],
                'features' => array_map(function ($feature) use ($minimum, $range) {
                    $feature['properties']['weight'] = $range > 0.000001
                        ? max(0.0, min(1.0, ((float) $feature['properties']['value'] - $minimum) / $range))
                        : 0.5;

                    return $feature;
                }, $features),
            ];
        }

        if ($requestedType && array_key_exists($requestedType, $series)) {
            return [$requestedType => $series[$requestedType]];
        }

        return $series;
    }

    private function pointFeature(array $coordinates, array $properties, float $value): array
    {
        return [
            'type' => 'Feature',
            'properties' => [...$properties, 'value' => $value],
            'geometry' => ['type' => 'Point', 'coordinates' => $coordinates],
        ];
    }

    private function aggregateSpatially(array $features, float $gridSize, bool $average = false): array
    {
        $cells = [];
        foreach ($features as $feature) {
            [$longitude, $latitude] = $feature['geometry']['coordinates'];
            $key = round($longitude / $gridSize).':'.round($latitude / $gridSize);
            $cells[$key] ??= ['longitude' => 0.0, 'latitude' => 0.0, 'value' => 0.0, 'count' => 0, 'names' => [], 'via_ids' => []];
            $cells[$key]['longitude'] += $longitude;
            $cells[$key]['latitude'] += $latitude;
            $cells[$key]['value'] += (float) $feature['properties']['value'];
            $cells[$key]['count']++;
            if (filled($feature['properties']['name'] ?? null)) {
                $cells[$key]['names'][$feature['properties']['name']] = true;
            }
            if (isset($feature['properties']['viaId'])) {
                $cells[$key]['via_ids'][(int) $feature['properties']['viaId']] = true;
            }
        }

        return array_values(array_map(function (array $cell) use ($average): array {
            $value = $average ? $cell['value'] / $cell['count'] : $cell['value'];
            $names = array_keys($cell['names']);

            return $this->pointFeature(
                [$cell['longitude'] / $cell['count'], $cell['latitude'] / $cell['count']],
                [
                    'name' => $names ? implode(', ', array_slice($names, 0, 3)) : $cell['count'].' ponto(s) nesta área',
                    'streets' => count($names),
                    'samples' => $cell['count'],
                    'viaIds' => array_keys($cell['via_ids']),
                ],
                $value,
            );
        }, $cells));
    }

    private function geometrySamples(mixed $geometry, float $spacingMeters): array
    {
        if (is_string($geometry)) {
            $geometry = json_decode($geometry, true);
        }
        if (($geometry['type'] ?? null) === 'Feature') {
            $geometry = $geometry['geometry'] ?? null;
        }
        if (! $geometry) {
            return [];
        }

        return $this->lineSampler->sample($geometry, $spacingMeters);
    }
}
