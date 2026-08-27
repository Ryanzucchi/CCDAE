<?php

namespace Tests\Feature\Api;

use App\Models\Distrito;
use App\Models\Poste;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergyAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_energy_analytics_returns_density_consumption_and_population(): void
    {
        $user = User::factory()->create();
        $district = Distrito::query()->create([
            'nome' => 'Centro', 'cidade' => 'Cáceres', 'latitude' => -16.07, 'longitude' => -57.68,
            'populacao' => 1000, 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[-57.69, -16.08], [-57.67, -16.08], [-57.67, -16.06], [-57.69, -16.08]]]],
        ]);
        Poste::query()->create(['distrito_id' => $district->id, 'latitude' => -16.071, 'longitude' => -57.681]);
        Poste::query()->create(['distrito_id' => $district->id, 'latitude' => -16.0711, 'longitude' => -57.6811]);

        $response = $this->actingAs($user)->getJson('/api/energia/analiticos');
        $response->assertOk()->assertJsonStructure([
            'densidade' => ['type', 'properties', 'features'],
            'consumo' => ['type', 'properties', 'features'],
            'populacao' => ['type', 'properties', 'features'],
        ])->assertJsonPath('populacao.features.0.properties.value', 1000)
            ->assertJsonPath('consumo.features.0.properties.estimado', true);

        $this->assertGreaterThan(1, count($response->json('populacao.features')));
        foreach ($response->json('populacao.features') as $feature) {
            $this->assertSame(1000, $feature['properties']['value']);
            $this->assertSame('district-distributed', $feature['properties']['spatialModel']);
        }
        $this->assertCount(1, $response->json('densidade.features'));
        $this->assertSame(2, $response->json('densidade.features.0.properties.value'));
        $this->assertSame('pole-density-grid', $response->json('densidade.features.0.properties.spatialModel'));
    }

    public function test_energy_analytics_can_return_only_population(): void
    {
        $user = User::factory()->create();
        Distrito::query()->create([
            'nome' => 'Centro', 'cidade' => 'Cáceres', 'latitude' => -16.07, 'longitude' => -57.68,
            'populacao' => 1000, 'geojson' => ['type' => 'Polygon', 'coordinates' => [[[-57.69, -16.08], [-57.67, -16.08], [-57.67, -16.06], [-57.69, -16.08]]]],
        ]);

        $response = $this->actingAs($user)->getJson('/api/energia/analiticos?tipo=populacao');

        $response->assertOk()
            ->assertJsonPath('populacao.features.0.properties.value', 1000)
            ->assertJsonMissingPath('densidade')
            ->assertJsonMissingPath('consumo');
    }
}
