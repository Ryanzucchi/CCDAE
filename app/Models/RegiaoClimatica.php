<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RegiaoClimatica extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
    protected $table = 'regioes_climaticas';


    protected $fillable = [
        'nome'
    ];

    public function distritos()
    {
        return $this->belongsToMany(Distrito::class);
    }
}
