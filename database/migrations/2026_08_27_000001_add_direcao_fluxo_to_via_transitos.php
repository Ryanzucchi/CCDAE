<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('via_transitos', 'direcao_fluxo')) {
            Schema::table('via_transitos', function (Blueprint $table): void {
                $table->string('direcao_fluxo')->default('ambos')->after('sentido');
            });
        }

        DB::table('via_transitos')
            ->where('sentido', 'unica')
            ->update(['direcao_fluxo' => 'inicio_fim']);

        DB::table('via_transitos')
            ->select(['id', 'infraestrutura_json'])
            ->whereNotNull('infraestrutura_json')
            ->orderBy('id')
            ->chunkById(500, function ($roads): void {
                foreach ($roads as $road) {
                    $metadata = is_string($road->infraestrutura_json)
                        ? json_decode($road->infraestrutura_json, true)
                        : (array) $road->infraestrutura_json;
                    if (($metadata['osm']['tags']['oneway'] ?? null) === '-1') {
                        DB::table('via_transitos')->where('id', $road->id)->update(['direcao_fluxo' => 'fim_inicio']);
                    }
                }
            });

        $this->replaceTileView(true);
    }

    public function down(): void
    {
        $this->replaceTileView(false);

        Schema::table('via_transitos', function (Blueprint $table): void {
            $table->dropColumn('direcao_fluxo');
        });
    }

    private function replaceTileView(bool $withDirection): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (! $withDirection) {
            DB::statement('DROP VIEW IF EXISTS tiles.vias');
        }

        $direction = $withDirection ? ', direcao_fluxo' : '';
        DB::statement("CREATE OR REPLACE VIEW tiles.vias AS SELECT id, distrito_id, nome, nivel_congestionamento, velocidade_media, geom{$direction} FROM mobility.via_transitos WHERE geom IS NOT NULL");
        DB::statement(<<<'SQL'
            COMMENT ON VIEW tiles.vias IS '{"description":"Malha viária e trânsito do CCDAE","minzoom":8,"maxzoom":22}'
            SQL);
    }
};
