<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Cabeamento extends Model
{
    use LogsActivity;

    protected $table = 'cabeamentos';
    protected $guarded = [];

    protected $casts = [
        'data_instalacao' => 'date',
        'ultima_manutencao' => 'date',
        'geojson' => 'array',
        'subterraneo' => 'boolean'
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

    public function distrito()
    {
        return $this->belongsTo(Distrito::class);
    }

    public function posteOrigem()
    {
        return $this->belongsTo(Poste::class, 'poste_origem_id');
    }

    public function posteDestino()
    {
        return $this->belongsTo(Poste::class, 'poste_destino_id');
    }

    public function centralOrigem()
    {
        return $this->belongsTo(CentralDistribuicao::class, 'central_origem_id');
    }

    public function centralDestino()
    {
        return $this->belongsTo(CentralDistribuicao::class, 'central_destino_id');
    }
}
