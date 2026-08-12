<?php

namespace Tests\Feature;

use App\Models\IncidenteEnergia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnergyIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_can_reference_an_energy_asset_location(): void
    {
        $incident = IncidenteEnergia::query()->create([
            'tipo' => 'surto', 'causa' => 'sobrecarga', 'severidade' => 'alta', 'situacao' => 'registrado',
            'latitude' => -16.07, 'longitude' => -57.68, 'ocorrido_em' => now(),
        ]);

        $this->assertDatabaseHas('incidentes_energia', ['id' => $incident->id, 'tipo' => 'surto', 'causa' => 'sobrecarga']);
    }
}
