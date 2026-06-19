<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class CentralDistribuicao extends Model
{
    use LogsActivity;

    protected $table = 'centrais_distribuicao';
    protected $guarded = [];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'data_instalacao' => 'date',
        'ultima_manutencao' => 'date',
        'geojson' => 'array'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }

    public function equipamentos()
    {
        return $this->hasMany(EquipamentoInfraestrutura::class, 'central_id');
    }
}
