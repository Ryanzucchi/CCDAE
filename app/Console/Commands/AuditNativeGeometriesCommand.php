<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditNativeGeometriesCommand extends Command
{
    protected $signature = 'geo:audit-native {--fail-on-drift : Return a failure status when inconsistencies are found}';

    protected $description = 'Audit native PostGIS geometries against the current GeoJSON and coordinate sources';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('A auditoria de geometrias nativas requer uma conexão PostgreSQL/PostGIS.');

            return self::FAILURE;
        }

        $definitions = [
            'distritos' => [
                'source' => 'geojson IS NOT NULL',
                'types' => ['ST_MultiPolygon'],
            ],
            'via_transitos' => [
                'source' => 'geojson IS NOT NULL',
                'types' => ['ST_MultiLineString'],
            ],
            'cabeamentos' => [
                'source' => 'geojson IS NOT NULL',
                'types' => ['ST_MultiLineString'],
            ],
            'centrais_distribuicao' => [
                'source' => '(geojson IS NOT NULL OR (latitude IS NOT NULL AND longitude IS NOT NULL))',
                'types' => ['ST_Point', 'ST_Polygon', 'ST_MultiPolygon', 'ST_LineString', 'ST_MultiLineString'],
            ],
            'postes' => [
                'source' => '(latitude IS NOT NULL AND longitude IS NOT NULL)',
                'types' => ['ST_Point'],
            ],
            'antenas' => [
                'source' => '(latitude IS NOT NULL AND longitude IS NOT NULL)',
                'types' => ['ST_Point'],
            ],
            'equipamentos_infraestrutura' => [
                'source' => '(latitude IS NOT NULL AND longitude IS NOT NULL)',
                'types' => ['ST_Point'],
            ],
        ];

        $rows = [];
        $hasDrift = false;

        foreach ($definitions as $table => $definition) {
            if (! Schema::hasColumn($table, 'geom')) {
                $rows[] = [$table, '-', '-', '-', '-', 'coluna geom ausente'];
                $hasDrift = true;

                continue;
            }

            $allowedTypes = implode(', ', array_fill(0, count($definition['types']), '?'));
            $result = DB::selectOne(
                "SELECT
                    COUNT(*) FILTER (WHERE {$definition['source']}) AS fontes,
                    COUNT(*) FILTER (WHERE {$definition['source']} AND geom IS NULL) AS sem_geom,
                    COUNT(*) FILTER (WHERE geom IS NOT NULL AND NOT ST_IsValid(geom)) AS invalidas,
                    COUNT(*) FILTER (WHERE geom IS NOT NULL AND ST_SRID(geom) <> 4326) AS srid_incorreto,
                    COUNT(*) FILTER (WHERE geom IS NOT NULL AND ST_GeometryType(geom) NOT IN ({$allowedTypes})) AS tipo_incorreto
                 FROM {$table}",
                $definition['types'],
            );

            $tableHasDrift = (int) $result->sem_geom > 0
                || (int) $result->invalidas > 0
                || (int) $result->srid_incorreto > 0
                || (int) $result->tipo_incorreto > 0;
            $hasDrift = $hasDrift || $tableHasDrift;

            $rows[] = [
                $table,
                (int) $result->fontes,
                (int) $result->sem_geom,
                (int) $result->invalidas,
                (int) $result->srid_incorreto,
                (int) $result->tipo_incorreto,
            ];
        }

        $this->table(
            ['Tabela', 'Com fonte', 'Sem geom', 'Inválidas', 'SRID incorreto', 'Tipo incorreto'],
            $rows,
        );

        if ($hasDrift) {
            $this->warn('A auditoria encontrou divergências geoespaciais.');

            return $this->option('fail-on-drift') ? self::FAILURE : self::SUCCESS;
        }

        $this->info('Todas as geometrias nativas estão sincronizadas, válidas e no SRID 4326.');

        return self::SUCCESS;
    }
}
