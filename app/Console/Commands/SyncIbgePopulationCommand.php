<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SyncIbgePopulationCommand extends Command
{
    protected $signature = 'ibge:sync-population {--force-download : Baixar novamente o GeoPackage oficial}';
    protected $description = 'Preenche a população dos distritos locais usando setores do Censo 2022 do IBGE';
    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') { $this->error('A sincronização requer PostgreSQL/PostGIS.'); return self::FAILURE; }
        $relative = 'ibge/MT_setores_CD2022.gpkg';
        if ($this->option('force-download') || !Storage::disk('local')->exists($relative)) {
            $this->info('Baixando malha oficial do Censo 2022 para Mato Grosso...');
            Storage::disk('local')->makeDirectory('ibge');
            $response = Http::timeout(600)->retry(3, 3000)->withOptions(['sink' => Storage::disk('local')->path($relative)])->get(config('services.ibge.census_sectors_url'));
            if (!$response->successful()) throw new RuntimeException('O IBGE respondeu HTTP '.$response->status());
        }
        $path = Storage::disk('local')->path($relative);
        DB::statement('DROP TABLE IF EXISTS geo.ibge_setores_population CASCADE');
        $connection = config('database.connections.pgsql');
        $dsn = sprintf('PG:host=%s port=%s dbname=%s user=%s password=%s', $connection['host'], $connection['port'], $connection['database'], $connection['username'], $connection['password']);
        $this->info('Importando setores para o PostGIS...');
        $result = Process::timeout(600)->run(['ogr2ogr', '-f', 'PostgreSQL', $dsn, $path, '-nln', 'geo.ibge_setores_population', '-overwrite', '-nlt', 'PROMOTE_TO_MULTI', '-t_srs', 'EPSG:4326', '-lco', 'GEOMETRY_NAME=geom']);
        if ($result->failed()) { $this->error($result->errorOutput() ?: $result->output()); return self::FAILURE; }

        $columns = collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_schema='geo' AND table_name='ibge_setores_population'"))->pluck('column_name');
        $populationColumn = collect(['v0001', 'v00001', 'cd_populacao', 'populacao', 'pessoas'])->first(fn ($column) => $columns->contains($column));
        if (!$populationColumn) throw new RuntimeException('Coluna de população não reconhecida. Colunas: '.$columns->implode(', '));

        DB::statement('CREATE INDEX IF NOT EXISTS ibge_setores_population_geom_gist ON geo.ibge_setores_population USING GIST (geom)');
        $updated = DB::affectingStatement(<<<SQL
            WITH calculated AS (
                SELECT d.id,
                       ROUND(SUM(COALESCE(s.{$populationColumn}, 0)::numeric *
                           ST_Area(ST_Intersection(d.geom, s.geom)::geography) /
                           NULLIF(ST_Area(s.geom::geography), 0)))::bigint AS population
                FROM geo.distritos d
                JOIN geo.ibge_setores_population s ON d.geom && s.geom AND ST_Intersects(d.geom, s.geom)
                WHERE d.geom IS NOT NULL
                GROUP BY d.id
            )
            UPDATE geo.distritos d SET populacao = c.population,
                populacao_fonte = 'IBGE Censo Demográfico 2022 — setores censitários',
                populacao_atualizada_em = NOW(), updated_at = NOW()
            FROM calculated c WHERE d.id = c.id
            SQL);
        $this->info("Distritos atualizados: {$updated}");
        $this->table(['Distrito', 'População'], DB::table('distritos')->orderByDesc('populacao')->get(['nome', 'populacao'])->map(fn ($d) => [$d->nome, number_format($d->populacao, 0, ',', '.')])->all());
        return self::SUCCESS;
    }
}
