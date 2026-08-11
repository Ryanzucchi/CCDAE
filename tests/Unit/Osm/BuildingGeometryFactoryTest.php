<?php

namespace Tests\Unit\Osm;

use App\Services\Osm\BuildingGeometryFactory;
use PHPUnit\Framework\TestCase;

class BuildingGeometryFactoryTest extends TestCase
{
    public function test_way_polygon_is_converted_to_multipolygon(): void
    {
        $geometry = (new BuildingGeometryFactory)->fromElement([
            'type' => 'way', 'geometry' => [
                ['lon' => -57.1, 'lat' => -16.1], ['lon' => -57.0, 'lat' => -16.1],
                ['lon' => -57.0, 'lat' => -16.0], ['lon' => -57.1, 'lat' => -16.1],
            ],
        ]);
        $this->assertSame('MultiPolygon', $geometry['type']);
        $this->assertCount(1, $geometry['coordinates']);
    }

    public function test_relation_builds_multipolygon_with_inner_ring(): void
    {
        $geometry = (new BuildingGeometryFactory)->fromElement([
            'type' => 'relation', 'members' => [
                ['type' => 'way', 'role' => 'outer', 'geometry' => $this->ring(-57.2, -16.2, -57.0, -16.0)],
                ['type' => 'way', 'role' => 'inner', 'geometry' => $this->ring(-57.15, -16.15, -57.05, -16.05)],
            ],
        ]);
        $this->assertSame('MultiPolygon', $geometry['type']);
        $this->assertCount(2, $geometry['coordinates'][0]);
    }

    private function ring(float $west, float $south, float $east, float $north): array
    {
        return [
            ['lon' => $west, 'lat' => $south], ['lon' => $east, 'lat' => $south],
            ['lon' => $east, 'lat' => $north], ['lon' => $west, 'lat' => $north],
            ['lon' => $west, 'lat' => $south],
        ];
    }
}
