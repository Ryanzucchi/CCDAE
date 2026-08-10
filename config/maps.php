<?php

return [
    'engine' => env('MAP_ENGINE', 'maplibre'),

    'center' => [
        'latitude' => (float) env('MAP_CENTER_LATITUDE', -15.7801),
        'longitude' => (float) env('MAP_CENTER_LONGITUDE', -47.9292),
    ],

    'zoom' => (float) env('MAP_DEFAULT_ZOOM', 4),

    // A full vector style URL takes precedence. Otherwise MapLibre builds a
    // minimal raster style from the configurable OSM-compatible tile URL.
    'base_style_url' => env('MAP_BASE_STYLE_URL'),
    'raster_tile_url' => env('MAP_RASTER_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'raster_dark_tile_url' => env('MAP_RASTER_DARK_TILE_URL', 'https://a.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}.png'),
    'raster_attribution' => env('MAP_RASTER_ATTRIBUTION', '&copy; OpenStreetMap contributors &copy; CARTO'),

    'terrain' => [
        'enabled' => (bool) env('MAP_TERRAIN_ENABLED', true),
        'terrarium_tile_url' => env('MAP_TERRAIN_TILE_URL', 'https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'),
        'zoom' => (int) env('MAP_TERRAIN_ZOOM', 12),
        'exaggeration' => (float) env('MAP_TERRAIN_EXAGGERATION', 1.35),
        'attribution' => env('MAP_TERRAIN_ATTRIBUTION', 'Mapzen Terrain Tiles / AWS Open Data'),
    ],

    'martin' => [
        'enabled' => (bool) env('MAP_MARTIN_ENABLED', true),
        'tilejson_base_url' => env('MAP_MARTIN_TILEJSON_URL', '/tiles'),
        'internal_catalog_url' => env('MARTIN_INTERNAL_CATALOG_URL', 'http://martin:3000/catalog'),
    ],
];
