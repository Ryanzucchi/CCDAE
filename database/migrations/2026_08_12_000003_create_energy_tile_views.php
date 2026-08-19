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
            CREATE OR REPLACE VIEW tiles.energia_cabeamentos AS
            SELECT id, distrito_id, nome, codigo_patrimonio, tipo_cabo, capacidade, revestimento,
                   subterraneo, extensao_metros, estado_conservacao, poste_origem_id, poste_destino_id,
                   central_origem_id, central_destino_id, geom
            FROM infrastructure.cabeamentos
            WHERE geom IS NOT NULL AND tipo_cabo IN ('eletrico_baixa_tensao', 'eletrico_alta_tensao');
            CREATE OR REPLACE VIEW tiles.energia_postes AS
            SELECT id, distrito_id, codigo_patrimonio, material, altura_metros, resistencia_kg,
                   possui_iluminacao, estado_conservacao, geom FROM infrastructure.postes WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.energia_equipamentos AS
            SELECT id, distrito_id, poste_id, central_id, nome, codigo_patrimonio, tipo, modelo,
                   estado_conservacao, geom FROM infrastructure.equipamentos_infraestrutura
            WHERE geom IS NOT NULL AND LOWER(tipo) IN ('transformador', 'medidor');
            CREATE OR REPLACE VIEW tiles.energia_centrais AS
            SELECT id, distrito_id, nome, codigo_patrimonio, tipo, capacidade, estado_conservacao, geom
            FROM infrastructure.centrais_distribuicao WHERE geom IS NOT NULL;
            COMMENT ON VIEW tiles.energia_cabeamentos IS '{"description":"Cabeamentos elétricos do CCDAE","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.energia_postes IS '{"description":"Postes de energia do CCDAE","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.energia_equipamentos IS '{"description":"Equipamentos elétricos do CCDAE","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.energia_centrais IS '{"description":"Centrais de energia do CCDAE","minzoom":10,"maxzoom":22}';
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS tiles.energia_cabeamentos, tiles.energia_postes, tiles.energia_equipamentos, tiles.energia_centrais');
        }
    }
};
