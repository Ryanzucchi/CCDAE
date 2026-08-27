<?php

return [
    'analytics_cache_seconds' => (int) env('ANALYTICS_CACHE_SECONDS', 300),
    'antennas' => [
        'overpass_url' => env('ANTENNA_OVERPASS_URL', 'https://overpass.kumi.systems/api/interpreter'),
        'timeout' => (int) env('ANTENNA_OVERPASS_TIMEOUT', 120),
    ],
    'traffic' => [
        'provider' => env('TRAFFIC_DATA_PROVIDER', 'tomtom'),
        'tomtom_key' => env('TOMTOM_TRAFFIC_API_KEY'),
        'timeout' => (int) env('TRAFFIC_API_TIMEOUT', 10),
        'provider_cooldown_seconds' => (int) env('TRAFFIC_PROVIDER_COOLDOWN_SECONDS', 3600),
        'max_segments_per_run' => (int) env('TRAFFIC_MAX_SEGMENTS_PER_RUN', 250),
        'vector_zoom' => max(0, min(22, (int) env('TOMTOM_VECTOR_FLOW_ZOOM', 13))),
        'vector_max_tiles_per_run' => max(1, (int) env('TOMTOM_VECTOR_MAX_TILES_PER_RUN', 64)),
        'daily_request_limit' => max(1, (int) env('TOMTOM_TRAFFIC_DAILY_REQUEST_LIMIT', 10000)),
        'road_match_distance_meters' => max(5, (int) env('TOMTOM_ROAD_MATCH_DISTANCE_METERS', 80)),
    ],
];
