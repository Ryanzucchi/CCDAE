<?php

namespace App\Services\Osm\Contracts;

interface BuildingProvider
{
    /** @return array<int, array<string, mixed>> */
    public function buildingsForBbox(array $bbox): array;
}
