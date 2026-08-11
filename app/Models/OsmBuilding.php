<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OsmBuilding extends Model
{
    protected $fillable = [
        'osm_id', 'osm_type', 'building', 'name', 'height', 'building_levels',
        'min_height', 'building_min_level', 'render_height', 'render_min_height',
        'height_source', 'tags', 'synced_at',
    ];

    protected $casts = [
        'height' => 'float', 'building_levels' => 'float', 'min_height' => 'float',
        'building_min_level' => 'float', 'render_height' => 'float',
        'render_min_height' => 'float', 'tags' => 'array', 'synced_at' => 'datetime',
    ];
}
