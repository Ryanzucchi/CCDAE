<?php

namespace App\Services\Analytics;

use App\Models\Distrito;
use App\Services\Geo\DensityGrid;
use App\Services\Geo\FeatureCollectionFactory;
use App\Services\Geo\PolygonSampler;
use Illuminate\Support\Facades\DB;

class EnergyAnalyticsService
{
    public function __construct(private readonly PolygonSampler $polygons, private readonly FeatureCollectionFactory $collections) {}

    public function build(): array
    {
        $districts = Distrito::query()->whereNotNull('geojson')->get(['id', 'nome', 'geojson', 'latitude', 'longitude', 'populacao', 'consumo_medio_kwh']);
        $posts = DB::table('postes')->selectRaw('distrito_id, count(*) total')->groupBy('distrito_id')->pluck('total', 'distrito_id');
        $cables = DB::table('cabeamentos')->whereIn('tipo_cabo', ['eletrico_baixa_tensao', 'eletrico_alta_tensao'])->selectRaw('distrito_id, count(*) total')->groupBy('distrito_id')->pluck('total', 'distrito_id');
        $data = ['densidade' => $this->density(), 'consumo' => [], 'populacao' => []];
        foreach ($districts as $district) {
            $samples = $this->polygons->sample($district->geojson, (float) $district->longitude, (float) $district->latitude);
            $population = (float) ($district->populacao ?? 0); $consumption = $district->consumo_medio_kwh !== null ? (float) $district->consumo_medio_kwh : $population * .18;
            foreach (['consumo' => $consumption, 'populacao' => $population] as $type => $value) foreach ($samples as $index => $coordinates) {
                $data[$type][] = ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => $coordinates], 'properties' => [
                    'districtId' => $district->id, 'name' => $district->nome, 'value' => $value, 'sample' => $index + 1, 'samples' => count($samples),
                    'spatialModel' => 'district-distributed', 'postes' => (int) ($posts[$district->id] ?? 0), 'cabos' => (int) ($cables[$district->id] ?? 0),
                    'populacao' => (int) $population, 'estimado' => $type === 'consumo' && $district->consumo_medio_kwh === null,
                ]];
            }
        }
        return array_map($this->collections->normalized(...), $data);
    }

    private function density(): array
    {
        $grid = new DensityGrid;
        DB::table('postes')->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('id')->select(['id', 'latitude', 'longitude'])->chunkById(5000,
            fn ($posts) => $posts->each(fn ($post) => $grid->add((float) $post->longitude, (float) $post->latitude, 'postes')));
        return $grid->features('Concentração local de postes', ['postes'], ['spatialModel' => 'pole-density-grid', 'cellSizeMeters' => 125]);
    }
}
