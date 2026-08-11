<?php

return [
    'buildings' => [
        'meters_per_level' => (float) env('OSM_BUILDING_METERS_PER_LEVEL', 3.0),
        'default_height' => (float) env('OSM_BUILDING_DEFAULT_HEIGHT', 6.0),
        'overpass_url' => env('OSM_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'timeout' => (int) env('OSM_OVERPASS_TIMEOUT', 90),
        'max_bbox_area_degrees' => (float) env('OSM_BUILDING_MAX_BBOX_AREA', 0.25),
        'viewport_max_area_degrees' => (float) env('OSM_BUILDING_VIEWPORT_MAX_AREA', 0.08),
    ],
];
