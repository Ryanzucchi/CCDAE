<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViaTransito extends Model
{
    use HasFactory;

    protected $table = 'via_transitos';

    protected $fillable = [
        'distrito_id',
        'nome',
        'geojson',
        'nivel_congestionamento',
        'velocidade_media',
        'volume_veiculos',
        'impacto_manutencao',
        'ultima_atualizacao'
    ];

    protected $casts = [
        'geojson' => 'array',
        'ultima_atualizacao' => 'datetime',
    ];

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }
}
