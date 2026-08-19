<?php

namespace Tests\Unit\Climate;

use App\Services\Climate\ClimateSimulationService;
use Tests\TestCase;

class ClimateSimulationServiceTest extends TestCase
{
    public function test_generates_weather_and_air_quality_simulation_arrays(): void
    {
        $service = new ClimateSimulationService;
        $distritos = collect([
            (object) ['id' => 1, 'latitude' => -16.07, 'longitude' => -57.68],
        ]);

        $weather = $service->generateWeatherData($distritos);
        $this->assertCount(1, $weather);
        $this->assertArrayHasKey('temperature_2m', $weather[0]['current']);
        $this->assertArrayHasKey('precipitation', $weather[0]['current']);

        $aq = $service->generateAirQualityData($distritos);
        $this->assertCount(1, $aq);
        $this->assertArrayHasKey('pm2_5', $aq[0]['current']);
        $this->assertArrayHasKey('dust', $aq[0]['current']);
    }
}
