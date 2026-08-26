<?php

namespace Tests\Unit\Traffic;

use App\Services\Traffic\OsmRoadAttributeMapper;
use PHPUnit\Framework\TestCase;

class OsmRoadAttributeMapperTest extends TestCase
{
    public function test_maps_explicit_osm_road_attributes(): void
    {
        $attributes = (new OsmRoadAttributeMapper)->map([
            'highway' => 'primary',
            'lanes' => '3',
            'oneway' => 'yes',
            'maxspeed' => '60 km/h',
            'surface' => 'asphalt',
            'incline' => '2.5%',
        ]);

        $this->assertSame('primary', $attributes['tipo']);
        $this->assertSame(3, $attributes['numero_faixas']);
        $this->assertSame('unica', $attributes['sentido']);
        $this->assertSame(60, $attributes['limite_velocidade']);
        $this->assertSame('asfalto', $attributes['estado_pavimento']);
        $this->assertSame(2.5, $attributes['inclinacao_pista']);
        $this->assertSame([], $attributes['_inferred']);
    }

    public function test_uses_auditable_defaults_when_osm_tags_are_absent(): void
    {
        $attributes = (new OsmRoadAttributeMapper)->map(['highway' => 'residential']);

        $this->assertSame(2, $attributes['numero_faixas']);
        $this->assertSame('dupla', $attributes['sentido']);
        $this->assertSame(30, $attributes['limite_velocidade']);
        $this->assertSame('desconhecido', $attributes['estado_pavimento']);
        $this->assertNull($attributes['inclinacao_pista']);
        $this->assertEqualsCanonicalizing(
            ['numero_faixas', 'limite_velocidade', 'estado_pavimento'],
            $attributes['_inferred'],
        );
    }

    public function test_converts_miles_per_hour_and_directional_lanes(): void
    {
        $attributes = (new OsmRoadAttributeMapper)->map([
            'highway' => 'secondary',
            'lanes:forward' => '2',
            'lanes:backward' => '1',
            'maxspeed' => '30 mph',
            'smoothness' => 'bad',
        ]);

        $this->assertSame(3, $attributes['numero_faixas']);
        $this->assertSame(48, $attributes['limite_velocidade']);
        $this->assertSame('ruim', $attributes['estado_pavimento']);
    }
}
