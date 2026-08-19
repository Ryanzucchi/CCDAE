<?php

namespace Tests\Unit\Geo;

use App\Services\Geo\DensityGrid;
use App\Services\Geo\FeatureCollectionFactory;
use App\Services\Geo\LineSampler;
use App\Services\Geo\PolygonSampler;
use PHPUnit\Framework\TestCase;

class SpatialServicesTest extends TestCase
{
    public function test_polygon_sampler_respects_holes(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [[[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]], [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]]]];
        $samples = (new PolygonSampler)->sample($geometry, 5, 5, 2, 5, 5);
        $this->assertNotEmpty($samples);
        $this->assertNotContains([5.0, 5.0], $samples);
    }

    public function test_line_sampler_distributes_points(): void
    {
        $samples = (new LineSampler)->sample(['type' => 'LineString', 'coordinates' => [[-57.68, -16.07], [-57.678, -16.07]]], 90);
        $this->assertGreaterThan(1, count($samples));
    }

    public function test_grid_and_collection_normalize_values(): void
    {
        $grid = new DensityGrid;
        $grid->add(-57.68, -16.07, 'cabos');
        $grid->add(-57.6801, -16.0701, 'cabos');
        $collection = (new FeatureCollectionFactory)->normalized($grid->features('Rede', ['cabos']));
        $this->assertSame(2, $collection['features'][0]['properties']['cabos']);
        $this->assertSame(.5, $collection['features'][0]['properties']['weight']);
    }
}
