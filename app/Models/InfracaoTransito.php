<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InfracaoTransito extends Model
{
    protected $table = 'infracoes_transito';

    protected $fillable = [
        'via_transito_id', 'distrito_id', 'registrado_por', 'codigo', 'tipo', 'placa',
        'descricao', 'valor', 'pontos', 'latitude', 'longitude', 'ocorrido_em', 'situacao',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
        'ocorrido_em' => 'datetime',
    ];

    public function via(): BelongsTo
    {
        return $this->belongsTo(ViaTransito::class, 'via_transito_id');
    }

    public function distrito(): BelongsTo
    {
        return $this->belongsTo(Distrito::class);
    }
}
