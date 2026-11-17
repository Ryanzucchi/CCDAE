<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function table(string $name): string
    {
        return (string) config('geoveil.table_prefix', 'geoveil_').$name;
    }

    private function nativeSpatialAvailable(): bool
    {
        $connection = DB::connection();

        return match ($connection->getDriverName()) {
            'mysql' => true,
            'pgsql' => in_array(DB::selectOne("select exists (select 1 from pg_extension where extname = 'postgis') as installed")?->installed, [true, 1, '1', 't'], true),
            default => false,
        };
    }

    public function up(): void
    {
        $mode = config('geoveil.spatial.driver', 'auto');
        if (! in_array($mode, ['auto', 'native', 'geojson'], true)) {
            throw new InvalidArgumentException("Modo espacial GeoVeil inválido: {$mode}.");
        }

        $native = $mode !== 'geojson' && $this->nativeSpatialAvailable();
        if ($mode === 'native' && ! $native) {
            throw new RuntimeException('O driver espacial nativo requer MySQL/MariaDB ou PostgreSQL com PostGIS habilitado.');
        }

        Schema::create($this->table('saved_geometries'), function (Blueprint $table) use ($native) {
            $table->id();
            $table->morphs('user');
            $table->string('map_key');
            $table->string('layer_key');
            $table->string('name')->nullable();
            $table->json('geometry_geojson');
            $table->json('properties')->nullable();
            if ($native) {
                $table->geometry('geometry', 'GEOMETRY', 4326)->nullable();
            }
            $table->timestamps();
            $table->index(['map_key', 'layer_key'], 'gv_saved_geom_map_layer_idx');
            $table->index(['user_type', 'user_id', 'map_key'], 'gv_saved_geom_user_map_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table('saved_geometries'));
    }
};
