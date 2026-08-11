<?php

namespace App\Console\Commands;

use App\Models\Distrito;
use App\Services\Geo\BoundingBox;
use App\Services\Osm\BuildingImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SyncOsmBuildingsCommand extends Command
{
    protected $signature = 'osm:sync-buildings
        {--district= : ID numérico do distrito}
        {--bbox= : minLng,minLat,maxLng,maxLat}
        {--list : Lista os distritos disponíveis}';
    protected $description = 'Sincroniza construções do OpenStreetMap para uma área limitada';

    public function handle(BuildingImportService $importer): int
    {
        if ($this->option('list')) {
            $this->table(['ID', 'Distrito'], Distrito::query()->orderBy('nome')->get(['id', 'nome'])->map(fn (Distrito $district) => [$district->id, $district->nome]));
            return self::SUCCESS;
        }
        $startedAt = microtime(true);
        try {
            $bbox = $this->resolveBbox();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Área: '.implode(',', $bbox));
        try {
            $stats = $importer->sync($bbox);
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->table(['Encontradas', 'Criadas', 'Atualizadas', 'Erros', 'Duração'], [[
            $stats['found'], $stats['created'], $stats['updated'], $stats['errors'], number_format(microtime(true) - $startedAt, 2, ',', '.').' s',
        ]]);
        foreach ($stats['error_messages'] ?? [] as $message) $this->warn($message);
        return $stats['errors'] > 0 ? self::INVALID : self::SUCCESS;
    }

    private function resolveBbox(): array
    {
        if ($this->option('bbox')) {
            return BoundingBox::parse($this->option('bbox'), (float) config('osm.buildings.max_bbox_area_degrees'));
        }
        $districtId = $this->option('district');
        if (!$districtId) throw new InvalidArgumentException('Informe --district com um ID numérico ou utilize --bbox. Use --list para consultar os distritos.');
        if (!ctype_digit((string) $districtId) || (int) $districtId < 1) {
            throw new InvalidArgumentException('O valor de --district deve ser um ID numérico. Use: php artisan osm:sync-buildings --list');
        }
        $district = Distrito::query()->find((int) $districtId);
        if (!$district) throw new InvalidArgumentException("Distrito {$districtId} não encontrado. Use --list para consultar os IDs disponíveis.");
        if (DB::getDriverName() === 'pgsql') {
            $extent = DB::selectOne('SELECT ST_XMin(geom) min_lng, ST_YMin(geom) min_lat, ST_XMax(geom) max_lng, ST_YMax(geom) max_lat FROM distritos WHERE id = ? AND geom IS NOT NULL', [$district->id]);
            if ($extent?->min_lng !== null) return [(float) $extent->min_lng, (float) $extent->min_lat, (float) $extent->max_lng, (float) $extent->max_lat];
        }
        $coordinates = $this->flattenCoordinates($district->geojson['coordinates'] ?? []);
        if (!$coordinates) throw new InvalidArgumentException('O distrito selecionado não possui geometria.');
        return [min(array_column($coordinates, 0)), min(array_column($coordinates, 1)), max(array_column($coordinates, 0)), max(array_column($coordinates, 1))];
    }

    private function flattenCoordinates(array $coordinates): array
    {
        if (isset($coordinates[0], $coordinates[1]) && is_numeric($coordinates[0]) && is_numeric($coordinates[1])) return [[$coordinates[0], $coordinates[1]]];
        return array_merge(...array_map(fn ($item) => is_array($item) ? $this->flattenCoordinates($item) : [], $coordinates));
    }
}
