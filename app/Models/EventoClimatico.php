<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventoClimatico extends Model
{
    protected $table = 'eventos_climaticos';

    protected $fillable = [
        'distrito_id',
        'tipo',
        'inicio',
        'fim',
        'descricao'
    ];

    protected $casts = [
        'inicio'=>'datetime',
        'fim'=>'datetime'
    ];

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }
}
