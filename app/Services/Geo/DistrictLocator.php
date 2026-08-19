<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\DB;

class DistrictLocator
{
    public function forPoint(float $longitude, float $latitude): ?int
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        return DB::table('distritos')->whereRaw('ST_Intersects(geom, ST_SetSRID(ST_Point(?, ?), 4326))', [$longitude, $latitude])->value('id');
    }
}
