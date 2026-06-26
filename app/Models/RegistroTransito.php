<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroTransito extends Model
{
    use HasFactory;

    protected $table = 'registro_transitos';

    // The primary key is composite (via_transito_id, timestamp)
    // TimescaleDB doesn't use the standard Eloquent auto-incrementing ID for hypertables
    public $incrementing = false;
    protected $primaryKey = 'timestamp';
    protected $keyType = 'string';
    // Hypertables in TimescaleDB often don't need created_at / updated_at
    // because timestamp serves that purpose natively, but Laravel provides them by default.
    // We'll leave timestamps enabled as the migration created them if we used `$table->timestamps()`.
    // Wait, in my migration I did NOT use `$table->timestamps()`. I will set `$timestamps = false`.
    public $timestamps = false;

    protected $fillable = [
        'via_transito_id',
        'timestamp',
        'veiculos_total',
        'velocidade_media',
        'velocidade_min',
        'velocidade_max',
        'tempo_medio_travessia',
        'altura_media_veiculos',
        'altura_maxima_veiculos',
        'peso_medio_veiculos',
        'peso_maximo_veiculos',
        'percentual_veiculos_pesados',
        'taxa_veiculos_eletricos',
        'acidentes_ativos',
        'obras_ativas',
        'alagamento_ativo',
        'chuva_mm',
        'visibilidade',
        'temperatura',
        'nivel_ruido',
        'emissao_co2',
        'indice_congestionamento',
        'nivel_servico',
        'dados_avancados'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'alagamento_ativo' => 'boolean',
        'dados_avancados' => 'array',
    ];

    public function viaTransito(): BelongsTo
    {
        return $this->belongsTo(ViaTransito::class);
    }
}
