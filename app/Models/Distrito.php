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
        return $this->belongsToMany(RegiaoClimatica::class)
                    ->withPivot('start_time', 'end_time');
    }

    public function regiaoClimatica()
    {
        return $this->belongsTo(RegiaoClimatica::class);
    }

    /**
     * Busca os dados climáticos para o timestamp especificado.
     * Se os dados do distrito tiverem sido sanitizados (agrupados numa Região),
     * busca automaticamente através da região correspondente.
     */
    public function getClimaAt($relation, $timestamp)
    {
        // 1. Tenta buscar no próprio distrito (caso não tenha sido agrupado/sanitizado)
        $record = $this->$relation()->where('timestamp', $timestamp)->first();
        if ($record) {
            return $record;
        }

        // 2. Se não encontrou, verifica se no momento estava agrupado numa região climática
        $regiao = $this->regioes()
            ->wherePivot('start_time', '<=', $timestamp)
            ->wherePivot('end_time', '>=', $timestamp)
            ->first();

        if ($regiao) {
            // Busca o distrito "primário" que manteve os dados dessa região nesse exato momento
            // Pode ser o primeiro distrito da região que não seja o atual e possua o dado
            $distritosDaRegiao = $regiao->distritos()
                ->where('distritos.id', '!=', $this->id)
                ->wherePivot('start_time', '<=', $timestamp)
                ->wherePivot('end_time', '>=', $timestamp)
                ->get();

            foreach ($distritosDaRegiao as $d) {
                $siblingRecord = $d->$relation()->where('timestamp', $timestamp)->first();
                if ($siblingRecord) {
                    return $siblingRecord;
                }
            }
        }

        return null;
    }

    public static function autoShrinkGeojson($geojsonArray, $ignoreId = null)
    {
        if (empty($geojsonArray)) return null;
        $geoJsonStr = is_string($geojsonArray) ? $geojsonArray : json_encode($geojsonArray);

        $unionQuery = "SELECT ST_AsGeoJSON(ST_Union(ST_SetSRID(ST_GeomFromGeoJSON(geojson::text), 4326))) as union_geom FROM distritos WHERE geojson IS NOT NULL";
        $bindings = [];
        if ($ignoreId) {
            $unionQuery .= " AND id != ?";
            $bindings[] = $ignoreId;
        }
        
        $union = \Illuminate\Support\Facades\DB::selectOne($unionQuery, $bindings);
        
        if ($union && $union->union_geom) {
            $diffQuery = "SELECT ST_AsGeoJSON(
                ST_Difference(
                    ST_SetSRID(ST_GeomFromGeoJSON(?), 4326),
                    ST_Buffer(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 0.00002)
                )
            ) as new_geom";
            $result = \Illuminate\Support\Facades\DB::selectOne($diffQuery, [$geoJsonStr, $union->union_geom]);
            
            if ($result && $result->new_geom) {
                // If the geometry becomes totally empty due to Difference, it returns null
                // We fallback to original if completely consumed or error, but wait, if it's completely consumed, new_geom might be valid EMPTY geometry.
                $parsed = json_decode($result->new_geom, true);
                if (isset($parsed['type']) && str_contains($parsed['type'], 'Polygon')) {
                    return $parsed;
                }
            }
        }
        return is_string($geojsonArray) ? json_decode($geojsonArray, true) : $geojsonArray;
    }
}
