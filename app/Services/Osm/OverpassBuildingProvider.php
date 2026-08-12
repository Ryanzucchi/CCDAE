<?php

namespace App\Services\Osm;

use App\Services\Osm\Contracts\BuildingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OverpassBuildingProvider implements BuildingProvider
{
    public function buildingsForBbox(array $bbox): array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $overpassBbox = implode(',', [$minLat, $minLng, $maxLat, $maxLng]);
        $query = "[out:json][timeout:60];(way[\"building\"]({$overpassBbox});relation[\"building\"]({$overpassBbox}););out tags geom;";
        $response = Http::asForm()->acceptJson()
            ->withHeaders(['User-Agent' => 'CCDAE-Infrastructure/1.0'])
            ->retry(3, 1500, throw: false)
            ->timeout((int) config('osm.buildings.timeout', 90))
            ->post(config('osm.buildings.overpass_url'), ['data' => $query]);
        if ($response->status() === 429) throw new RuntimeException('A Overpass API limitou temporariamente as consultas (HTTP 429).');
        if (! $response->successful()) throw new RuntimeException('A Overpass API respondeu HTTP '.$response->status().'.');
        return array_values(array_filter($response->json('elements', []), fn ($element) => isset($element['id'], $element['type'], $element['tags']['building'])));
    }
}
