<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Distrito extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions {
        return LogOptions::defaults()->logAll(); // Registra todas as mudanças
    }
    protected $table = 'distritos';

    protected $fillable = [
        'nome',
        'latitude',
        'longitude',
        'cidade',
        'geojson',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'geojson' => 'array',
    ];

    public function estacoes()
    {
        return $this->hasMany(EstacaoMeteorologica::class);
    }

    public function temperaturas()
    {
        return $this->hasMany(TemperaturaRegistrada::class);
    }

    public function ventos()
    {
        return $this->hasMany(VentoRegistrado::class);
    }

    public function chuvas()
    {
        return $this->hasMany(ChuvaRegistrada::class);
    }

    public function pressoes()
    {
        return $this->hasMany(PressaoAtmosferica::class);
    }

    public function radiacoes()
    {
        return $this->hasMany(RadiacaoSolar::class);
    }

    public function uv()
    {
        return $this->hasMany(IndiceUV::class);
    }

    public function particulas()
    {
        return $this->hasMany(ParticulaAr::class);
    }

    public function eventos()
    {
        return $this->hasMany(EventoClimatico::class);
    }

    public function regioes()
    {
        return $this->belongsToMany(RegiaoClimatica::class);
    }
}
