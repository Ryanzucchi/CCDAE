<?php

namespace Tests\Feature\Traffic;

use App\Models\ViaTransito;
use App\Services\Traffic\TrafficRoadMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficRoadMatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_matches_segment_and_detects_direction_relative_to_osm_geometry(): void
    {
        $road = ViaTransito::query()->create([
            'nome' => 'Via de teste',
            'geojson' => ['type' => 'LineString', 'coordinates' => [[-57.68, -16.07], [-57.67, -16.07]]],
            'nivel_congestionamento' => 'livre',
        ]);
        $matcher = new TrafficRoadMatcher;

        $forward = $matcher->match(['type' => 'LineString', 'coordinates' => [[-57.679, -16.0701], [-57.675, -16.0701]]]);
        $reverse = $matcher->match(['type' => 'LineString', 'coordinates' => [[-57.675, -16.0701], [-57.679, -16.0701]]]);

        $this->assertSame($road->id, $forward['via_transito_id']);
        $this->assertSame('inicio_fim', $forward['direcao_fluxo']);
        $this->assertSame('fim_inicio', $reverse['direcao_fluxo']);
    }
}
