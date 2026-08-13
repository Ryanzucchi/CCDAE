<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_climate_and_traffic_tables_have_provenance_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('registro_transitos', ['origem_dado', 'fonte_dado', 'confianca_dado']));
        foreach (['temperatura_registrada', 'vento_registrado', 'chuva_registrada', 'particulas_ar'] as $table) {
            $this->assertTrue(Schema::hasColumns($table, ['origem_dado', 'fonte_dado']));
        }
    }
}
