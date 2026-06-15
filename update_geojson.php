<?php
use Illuminate\Support\Facades\DB;

$distritos = DB::table('distritos')->get();

foreach ($distritos as $d) {
    if (!$d->latitude || !$d->longitude) continue;
    
    $lat = (float) $d->latitude;
    $lng = (float) $d->longitude;
    
    // Half width and height
    $w = 0.005; // Make the polygons large enough to see!
    $h = 0.005;
    
    $geojson = [
        "type" => "Polygon",
        "coordinates" => [
            [
                [$lng - $w, $lat - $h],
                [$lng + $w, $lat - $h],
                [$lng + $w, $lat + $h],
                [$lng - $w, $lat + $h],
                [$lng - $w, $lat - $h]
            ]
        ]
    ];
    
    DB::table('distritos')->where('id', $d->id)->update(['geojson' => json_encode($geojson)]);
}

echo "GeoJSON Atualizado!\n";
