<?php

return [
    'buildings' => [
        'meters_per_level' => (float) env('OSM_BUILDING_METERS_PER_LEVEL', 3.0),
        'default_height' => (float) env('OSM_BUILDING_DEFAULT_HEIGHT', 6.0),
        'provider' => 'osm_with_global_building_atlas_odbl',
        'global_atlas_path' => env('GLOBAL_BUILDING_ATLAS_PATH', storage_path('app/global-building-atlas')),
        'overpass_url' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'timeout' => (int) env('OSM_OVERPASS_TIMEOUT', 90),
        'deduplication_distance_degrees' => (float) env('BUILDING_DEDUPLICATION_DISTANCE_DEGREES', 0.00012),
        'max_bbox_area_degrees' => (float) env('OSM_BUILDING_MAX_BBOX_AREA', 0.25),
        'viewport_max_area_degrees' => (float) env('OSM_BUILDING_VIEWPORT_MAX_AREA', 0.08),
    ],
];
