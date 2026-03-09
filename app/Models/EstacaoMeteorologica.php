<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EstacaoMeteorologica extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
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
