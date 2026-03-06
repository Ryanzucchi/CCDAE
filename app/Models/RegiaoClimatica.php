<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegiaoClimatica extends Model
{
    protected $table = 'regioes_climaticas';

    protected $fillable = [
        'nome'
    ];

    public function distritos()
    {
        return $this->belongsToMany(Distrito::class);
    }
}
