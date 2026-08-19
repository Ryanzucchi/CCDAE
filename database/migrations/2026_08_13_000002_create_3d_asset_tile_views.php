<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE VIEW tiles.energia_postes_3d AS
            SELECT id, distrito_id, codigo_patrimonio, COALESCE(altura_metros, 9)::float AS render_height,
                   ST_Transform(ST_Buffer(ST_Transform(geom, 31981), 0.22, 'quad_segs=3'), 4326) AS geom
            FROM infrastructure.postes WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.energia_equipamentos_3d AS
            SELECT id, distrito_id, nome, tipo, CASE WHEN LOWER(tipo)='transformador' THEN 2.4 ELSE 1.5 END::float render_height,
                   ST_Transform(ST_Buffer(ST_Transform(geom, 31981), CASE WHEN LOWER(tipo)='transformador' THEN 0.75 ELSE 0.45 END, 'quad_segs=1'), 4326) AS geom
            FROM infrastructure.equipamentos_infraestrutura WHERE geom IS NOT NULL AND LOWER(tipo) IN ('transformador','medidor');
            CREATE OR REPLACE VIEW tiles.internet_antenas_3d AS
            SELECT id, distrito_id, codigo_patrimonio, tipo_sinal, 18::float render_height,
                   ST_Transform(ST_Buffer(ST_Transform(geom, 31981), 0.45, 'quad_segs=3'), 4326) AS geom
            FROM infrastructure.antenas WHERE geom IS NOT NULL;
            COMMENT ON VIEW tiles.energia_postes_3d IS '{"description":"Modelos 3D simplificados de postes","minzoom":15,"maxzoom":22}';
            COMMENT ON VIEW tiles.energia_equipamentos_3d IS '{"description":"Modelos 3D simplificados de equipamentos elétricos","minzoom":15,"maxzoom":22}';
            COMMENT ON VIEW tiles.internet_antenas_3d IS '{"description":"Modelos 3D simplificados de antenas","minzoom":14,"maxzoom":22}';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS tiles.energia_postes_3d, tiles.energia_equipamentos_3d, tiles.internet_antenas_3d');
        }
    }
};
