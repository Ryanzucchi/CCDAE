<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EventoClimatico extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
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
