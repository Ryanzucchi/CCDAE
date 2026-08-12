<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class IncidenteInternet extends Model
{
    protected $table = 'incidentes_internet';
    protected $guarded = [];
    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'ocorrido_em' => 'datetime', 'resolvido_em' => 'datetime'];

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'internet.incidentes_internet' : parent::getTable();
    }

    public function distrito(): BelongsTo { return $this->belongsTo(Distrito::class); }
    public function antena(): BelongsTo { return $this->belongsTo(Antena::class); }
    public function cabeamento(): BelongsTo { return $this->belongsTo(Cabeamento::class); }
    public function equipamento(): BelongsTo { return $this->belongsTo(EquipamentoInfraestrutura::class); }
    public function registradoPor(): BelongsTo { return $this->belongsTo(User::class, 'registrado_por'); }
}
