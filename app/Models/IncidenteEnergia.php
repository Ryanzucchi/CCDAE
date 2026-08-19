<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenteEnergia extends Model
{
    protected $table = 'incidentes_energia';

    protected $guarded = [];

    protected $casts = [
        'latitude' => 'float', 'longitude' => 'float',
        'ocorrido_em' => 'datetime', 'resolvido_em' => 'datetime',
    ];

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }

    public function poste(): BelongsTo
    {
        return $this->belongsTo(Poste::class);
    }

    public function cabeamento(): BelongsTo
    {
        return $this->belongsTo(Cabeamento::class);
    }

    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(EquipamentoInfraestrutura::class);
    }

    public function central(): BelongsTo
    {
        return $this->belongsTo(CentralDistribuicao::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
