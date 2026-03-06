<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticulaAr extends Model
{
    protected $table = 'particulas_ar';

    public $timestamps = false;

    protected $fillable = [
        'distrito_id',
        'timestamp',
        'pm25',
        'pm10',
        'poeira',
        'areia',
        'poluentes'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'pm25'=>'float',
        'pm10'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
