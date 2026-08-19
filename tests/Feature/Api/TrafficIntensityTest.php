<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ViaTransito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrafficIntensityTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_intensity_returns_valid_geo_feature_collections(): void
    {
        ViaTransito::create([
            'nome' => 'Avenida Principal',
            'geojson' => [
                'type' => 'LineString',
                'coordinates' => [
                    [-57.68, -16.07],
                    [-57.67, -16.06],
                ],
            ],
            'nivel_congestionamento' => 'livre',
            'velocidade_media' => 50,
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/transito/intensidade');

        $response->assertOk()->assertJsonStructure([
            'volume' => ['type', 'properties' => ['minimum', 'maximum', 'scale'], 'features'],
            'acidentes' => ['type', 'properties' => ['minimum', 'maximum', 'scale'], 'features'],
            'obras' => ['type', 'properties' => ['minimum', 'maximum', 'scale'], 'features'],
            'infracoes' => ['type', 'properties' => ['minimum', 'maximum', 'scale'], 'features'],
        ]);
    }

    public function test_traffic_intensity_validates_invalid_period(): void
    {
        $response = $this->actingAs(User::factory()->create())
            ->getJson('/api/transito/intensidade?inicio=2026-08-20&fim=2026-08-10');

        $response->assertStatus(422);
    }
}
