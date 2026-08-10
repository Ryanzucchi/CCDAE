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
            CREATE SCHEMA IF NOT EXISTS tiles;

            CREATE OR REPLACE VIEW tiles.distritos AS
                SELECT id, nome, cidade, geom
                FROM public.distritos
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.vias AS
                SELECT id, distrito_id, nome, nivel_congestionamento, velocidade_media, geom
                FROM public.via_transitos
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.cabeamentos AS
                SELECT id, distrito_id, nome, tipo_cabo, estado_conservacao,
                       poste_origem_id, poste_destino_id, central_origem_id, central_destino_id, geom
                FROM public.cabeamentos
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.centrais AS
                SELECT id, distrito_id, nome, tipo, estado_conservacao, geom
                FROM public.centrais_distribuicao
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.postes AS
                SELECT id, distrito_id, codigo_patrimonio, material, estado_conservacao, altura_metros, geom
                FROM public.postes
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.antenas AS
                SELECT id, distrito_id, codigo_patrimonio, tipo_sinal, estado_conservacao, geom
                FROM public.antenas
                WHERE geom IS NOT NULL;

            CREATE OR REPLACE VIEW tiles.equipamentos AS
                SELECT id, distrito_id, poste_id, central_id, nome, tipo, estado_conservacao, geom
                FROM public.equipamentos_infraestrutura
                WHERE geom IS NOT NULL;

            COMMENT ON VIEW tiles.distritos IS '{"description":"Limites dos distritos do CCDAE","minzoom":5,"maxzoom":22}';
            COMMENT ON VIEW tiles.vias IS '{"description":"Malha viária e trânsito do CCDAE","minzoom":8,"maxzoom":22}';
            COMMENT ON VIEW tiles.cabeamentos IS '{"description":"Rede de cabeamentos do CCDAE","minzoom":14,"maxzoom":22}';
            COMMENT ON VIEW tiles.centrais IS '{"description":"Centrais de distribuição do CCDAE","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.postes IS '{"description":"Postes do CCDAE","minzoom":15,"maxzoom":22}';
            COMMENT ON VIEW tiles.antenas IS '{"description":"Antenas do CCDAE","minzoom":13,"maxzoom":22}';
            COMMENT ON VIEW tiles.equipamentos IS '{"description":"Equipamentos de infraestrutura do CCDAE","minzoom":15,"maxzoom":22}';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP SCHEMA IF EXISTS tiles CASCADE');
    }
};
