<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TABLES = [
        'geo' => ['distritos'],
        'climate' => ['estacoes_meteorologicas', 'temperatura_registrada', 'vento_registrado', 'chuva_registrada', 'pressao_atmosferica', 'radiacao_solar', 'indice_uv', 'particulas_ar', 'eventos_climaticos', 'regioes_climaticas', 'regiao_climatica_distrito'],
        'infrastructure' => ['postes', 'antenas', 'centrais_distribuicao', 'equipamentos_infraestrutura', 'cabeamentos', 'osm_buildings'],
        'mobility' => ['via_transitos', 'registro_transitos', 'infracoes_transito'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::transaction(function (): void {
            foreach (array_keys(self::TABLES) as $schema) {
                DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
            }
            DB::statement('DROP SCHEMA IF EXISTS tiles CASCADE');
            foreach (self::TABLES as $schema => $tables) {
                foreach ($tables as $table) {
                    DB::statement("ALTER TABLE public.{$table} SET SCHEMA {$schema}");
                }
            }
            $this->createTileViews();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::transaction(function (): void {
            DB::statement('DROP SCHEMA IF EXISTS tiles CASCADE');
            foreach (self::TABLES as $schema => $tables) {
                foreach (array_reverse($tables) as $table) {
                    DB::statement("ALTER TABLE {$schema}.{$table} SET SCHEMA public");
                }
            }
            foreach (array_reverse(array_keys(self::TABLES)) as $schema) {
                DB::statement("DROP SCHEMA IF EXISTS {$schema}");
            }
            $this->createTileViews(true);
        });
    }

    private function createTileViews(bool $public = false): void
    {
        $geo = $public ? 'public' : 'geo';
        $infra = $public ? 'public' : 'infrastructure';
        $mobility = $public ? 'public' : 'mobility';
        DB::unprepared(<<<SQL
            CREATE SCHEMA IF NOT EXISTS tiles;
            CREATE OR REPLACE VIEW tiles.distritos AS SELECT id, nome, cidade, geom FROM {$geo}.distritos WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.vias AS SELECT id, distrito_id, nome, nivel_congestionamento, velocidade_media, geom FROM {$mobility}.via_transitos WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.cabeamentos AS SELECT id, distrito_id, nome, tipo_cabo, estado_conservacao, poste_origem_id, poste_destino_id, central_origem_id, central_destino_id, geom FROM {$infra}.cabeamentos WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.centrais AS SELECT id, distrito_id, nome, tipo, estado_conservacao, geom FROM {$infra}.centrais_distribuicao WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.postes AS SELECT id, distrito_id, codigo_patrimonio, material, estado_conservacao, altura_metros, geom FROM {$infra}.postes WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.antenas AS SELECT id, distrito_id, codigo_patrimonio, tipo_sinal, estado_conservacao, geom FROM {$infra}.antenas WHERE geom IS NOT NULL;
            CREATE OR REPLACE VIEW tiles.equipamentos AS SELECT id, distrito_id, poste_id, central_id, nome, tipo, estado_conservacao, geom FROM {$infra}.equipamentos_infraestrutura WHERE geom IS NOT NULL;
            COMMENT ON VIEW tiles.distritos IS '{"description":"Limites dos distritos do CCDAE","minzoom":5,"maxzoom":22}';
            COMMENT ON VIEW tiles.vias IS '{"description":"Malha viária e trânsito do CCDAE","minzoom":8,"maxzoom":22}';
            COMMENT ON VIEW tiles.cabeamentos IS '{"description":"Rede de cabeamentos do CCDAE","minzoom":14,"maxzoom":22}';
            COMMENT ON VIEW tiles.centrais IS '{"description":"Centrais de distribuição do CCDAE","minzoom":10,"maxzoom":22}';
            COMMENT ON VIEW tiles.postes IS '{"description":"Postes do CCDAE","minzoom":15,"maxzoom":22}';
            COMMENT ON VIEW tiles.antenas IS '{"description":"Antenas do CCDAE","minzoom":13,"maxzoom":22}';
            COMMENT ON VIEW tiles.equipamentos IS '{"description":"Equipamentos de infraestrutura do CCDAE","minzoom":15,"maxzoom":22}';
            SQL);
    }
};
