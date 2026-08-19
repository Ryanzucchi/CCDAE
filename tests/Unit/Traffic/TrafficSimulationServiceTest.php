<?php

namespace Tests\Unit\Traffic;

use App\Models\ViaTransito;
use App\Services\Traffic\TrafficSimulationService;
use Tests\TestCase;

class TrafficSimulationServiceTest extends TestCase
{
    public function test_simulates_via_snapshot_with_and_without_real_flow(): void
    {
        $service = new TrafficSimulationService;
        $via = new ViaTransito([
            'id' => 99,
            'limite_velocidade' => 60,
            'numero_faixas' => 2,
        ]);
        $via->id = 99;

        // Simulated
        $simulated = $service->simulateViaSnapshot($via);
        $this->assertArrayHasKey('registro', $simulated);
        $this->assertArrayHasKey('via_update', $simulated);
        $this->assertEquals('simulado', $simulated['registro']['origem_dado']);

        // Mixed with real flow
        $mixed = $service->simulateViaSnapshot($via, [
            'currentSpeed' => 35.0,
            'freeFlowSpeed' => 50.0,
            'confidence' => 0.95,
        ]);
        $this->assertEquals('misto', $mixed['registro']['origem_dado']);
        $this->assertEquals(35.0, $mixed['registro']['velocidade_media']);
        $this->assertEquals(30, $mixed['via_update']['nivel_congestionamento']);
    }
}
