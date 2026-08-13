<?php

namespace App\Services\Analytics;

use App\Models\Distrito;
use App\Services\Geo\DensityGrid;
use App\Services\Geo\FeatureCollectionFactory;
use App\Services\Geo\LineSampler;
use App\Services\Geo\PolygonSampler;
use Illuminate\Support\Facades\DB;

class InternetAnalyticsService
{
    public function __construct(private readonly PolygonSampler $polygons, private readonly LineSampler $lines, private readonly FeatureCollectionFactory $collections) {}

    public function build(): array
    {
        $demand = [];
        foreach (Distrito::query()->whereNotNull('geojson')->get(['id', 'nome', 'geojson', 'latitude', 'longitude', 'populacao']) as $district) {
            $population = (float) ($district->populacao ?? 0);
            foreach ($this->polygons->sample($district->geojson, (float) $district->longitude, (float) $district->latitude) as $coordinates) $demand[] = [
                'type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $coordinates],
                'properties' => ['districtId' => $district->id, 'name' => $district->nome, 'value' => round($population * .78), 'populacao' => (int) $population, 'estimado' => true, 'spatialModel' => 'population-distributed'],
            ];
        }
        return ['densidade_rede' => $this->collections->normalized($this->density()), 'demanda' => $this->collections->normalized($demand)];
    }

    private function density(): array
    {
        $grid = new DensityGrid;
        foreach (['antenas' => 'antenas', 'equipamentos_infraestrutura' => 'equipamentos'] as $table => $kind) DB::table($table)->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('id')->chunkById(3000,
            fn ($rows) => $rows->each(fn ($row) => $grid->add((float) $row->longitude, (float) $row->latitude, $kind)), 'id', 'id');
        $cables = DB::table('cabeamentos')->whereIn('tipo_cabo', ['fibra_optica', 'fibra_backbone', 'cobre_telefonia']);
        DB::getDriverName() === 'pgsql' ? $cables->whereNotNull('geom')->selectRaw("ST_AsGeoJSON(ST_SimplifyPreserveTopology(geom, 0.00003)) geometry") : $cables->whereNotNull('geojson')->selectRaw('geojson as geometry');
        $cables->orderBy('id')->chunk(1000, function ($rows) use ($grid): void { foreach ($rows as $row) {
            $geometry = is_string($row->geometry) ? json_decode($row->geometry, true) : (array) $row->geometry;
            foreach ($this->lines->sample($geometry, 90) as [$longitude, $latitude]) $grid->add($longitude, $latitude, 'cabos');
        }});
        return $grid->features('Concentração da rede', ['antenas', 'equipamentos', 'cabos'], ['cellSizeMeters' => 125]);
    }
}
