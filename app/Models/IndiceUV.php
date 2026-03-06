<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndiceUV extends Model
{
    protected $table = 'indice_uv';

    public $timestamps = false;

    protected $fillable = [
        'distrito_id',
        'timestamp',
        'uv'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'uv'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
