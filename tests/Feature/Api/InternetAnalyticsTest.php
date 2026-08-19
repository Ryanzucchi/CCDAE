<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_internet_analytics_returns_network_density_and_demand(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson('/api/internet/analiticos');

        $response->assertOk()->assertJsonStructure([
            'densidade_rede' => ['type', 'properties' => ['minimum', 'maximum'], 'features'],
            'demanda' => ['type', 'properties' => ['minimum', 'maximum'], 'features'],
        ]);
        $response->assertHeader('Cache-Control');
    }
}
