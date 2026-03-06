<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Distrito extends Model
{
    protected $table = 'distritos';

    protected $fillable = [
        'nome',
        'latitude',
        'longitude',
        'cidade'
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float'
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
