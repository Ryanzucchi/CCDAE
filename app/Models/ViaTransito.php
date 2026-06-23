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
        'ultima_atualizacao',
        'tipo',
        'numero_faixas',
        'sentido',
        'limite_velocidade',
        'inclinacao_pista',
        'altura_maxima_permitida',
        'largura_maxima_permitida',
        'peso_maximo_permitido',
        'pedagio',
        'estado_pavimento',
        'ciclovia',
        'faixas_exclusivas',
        'infraestrutura_json'
    ];

    protected $casts = [
        'geojson' => 'array',
        'ultima_atualizacao' => 'datetime',
        'pedagio' => 'boolean',
        'ciclovia' => 'boolean',
        'faixas_exclusivas' => 'boolean',
        'infraestrutura_json' => 'array',
    ];

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }

    public function registros()
    {
        return $this->hasMany(RegistroTransito::class);
    }
}
