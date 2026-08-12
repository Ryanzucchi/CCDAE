<?php

namespace Tests\Feature;

use App\Models\IncidenteInternet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternetIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_internet_incident_stores_location_and_affected_users(): void
    {
        $incident = IncidenteInternet::create([
            'tipo' => 'rompimento_fibra', 'causa' => 'obra', 'severidade' => 'alta',
            'situacao' => 'registrado', 'usuarios_afetados' => 420,
            'latitude' => -16.07083, 'longitude' => -57.68750, 'ocorrido_em' => now(),
        ]);

        $this->assertSame(420, $incident->usuarios_afetados);
        $this->assertSame(-16.07083, $incident->latitude);
    }
}
