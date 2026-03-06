<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstacaoMeteorologica extends Model
{
    protected $table = 'estacoes_meteorologicas';

    protected $fillable = [
        'distrito_id',
        'nome',
        'fonte_api',
        'latitude',
        'longitude'
    ];

    protected $casts = [
        'latitude'=>'float',
        'longitude'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
