<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Distrito;

class NoOverlappingDistrict implements ValidationRule
{
    protected ?int $ignoreId;

    public function __construct(?int $ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $value vem do Dotswan MapPicker, que tem ['lat', 'lng', 'geojson']
        if (empty($value) || !isset($value['geojson'])) {
            return;
        }

        $geojsonArray = $value['geojson'];
        // Remove empty coordinates if user cleared the map
        if (empty($geojsonArray)) {
            return;
        }

        // PostGIS ST_GeomFromGeoJSON não aceita FeatureCollection, apenas Geometrias (Polygon, Point, etc)
        if (isset($geojsonArray['type']) && $geojsonArray['type'] === 'FeatureCollection') {
            $geojsonArray = $geojsonArray['features'][0]['geometry'] ?? null;
            if (!$geojsonArray) return;
        }

        $geojson = json_encode($geojsonArray);

        // Verifica interseção no PostGIS: Intersecciona MAS NÃO toca (para permitir distritos vizinhos de muro)
        // O `geojson::text` transforma o campo JSON do postgres em texto para a função interpretar
        $query = Distrito::whereNotNull('geojson')
            ->whereRaw("ST_Intersects(
                ST_SetSRID(ST_GeomFromGeoJSON(?), 4326),
                ST_SetSRID(ST_GeomFromGeoJSON(geojson::text), 4326)
            )", [$geojson])
            ->whereRaw("NOT ST_Touches(
                ST_SetSRID(ST_GeomFromGeoJSON(?), 4326),
                ST_SetSRID(ST_GeomFromGeoJSON(geojson::text), 4326)
            )", [$geojson]);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail('A área territorial deste distrito se sobrepõe (invade) as fronteiras de outro distrito já cadastrado.');
        }
    }
}
