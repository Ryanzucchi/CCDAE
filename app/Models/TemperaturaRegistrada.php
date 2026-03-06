<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemperaturaRegistrada extends Model
{
    protected $table = 'temperatura_registrada';

    public $timestamps = false;

    protected $fillable = [
        'distrito_id',
        'timestamp',
        'temperatura',
        'sensacao_termica'
    ];

    protected $casts = [
        'timestamp'=>'datetime',
        'temperatura'=>'float',
        'sensacao_termica'=>'float'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
