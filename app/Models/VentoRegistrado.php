<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentoRegistrado extends Model
{
    protected $table = 'vento_registrado';

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = [
        'distrito_id',
        'timestamp',
        'velocidade',
        'direcao'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'velocidade'=>'float',
        'direcao'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
