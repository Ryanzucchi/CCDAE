<?php

return [
    /* Prefix applied to GeoVeil-owned structural table names by migrations. */
    'table_prefix' => env('GEOVEIL_TABLE_PREFIX', 'geoveil_'),

    /* Morph columns allow the consuming application to use its own user model. */
    'morphs' => [
        'users' => 'user',
    ],

    /* An optional default forward geocoder; no upstream is contacted by default. */
    'geocoding' => [
        'endpoint' => env('GEOVEIL_GEOCODER_ENDPOINT'),
        'user_agent' => env('GEOVEIL_GEOCODER_USER_AGENT', 'GeoVeil/2.0'),
    ],

    /* Optional measured scores by engine; higher is faster. */
    'engines' => [
        'scores' => [],
        'fallback' => 'maplibre',
    ],

    /* Geometry storage: auto uses native spatial columns when available; geojson is portable. */
    'spatial' => [
        'driver' => env('GEOVEIL_SPATIAL_DRIVER', 'auto'),
    ],
];
