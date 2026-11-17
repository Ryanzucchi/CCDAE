<?php

namespace App\Filament\Admin\Pages;

use App\Models\Distrito;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MapaClima extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Mapa do Clima';

    protected static ?string $title = 'Central de Monitoramento Climático';

    protected static ?string $slug = 'mapa-clima';

    protected string $view = 'filament.admin.pages.mapa-clima';

    public function getClimaDataProperty()
    {
        return collect($this->climaTimeline)->last()['data'] ?? [];
    }

    public function getClimaMapDataProperty(): array
    {
        return Cache::remember('mapa-clima:distritos-map-data', now()->addMinutes(10), function (): array {
            return Distrito::query()
                ->whereNotNull('geojson')
                ->get(['id', 'nome', 'latitude', 'longitude', 'geojson'])
                ->map(fn (Distrito $d) => [
                    'id' => $d->id,
                    'nome' => $d->nome,
                    'latitude' => (float) ($d->latitude ?: -16.070830),
                    'longitude' => (float) ($d->longitude ?: -57.687500),
                    'geojson' => $d->geojson,
                ])->all();
        });
    }

    public function getClimaTimelineProperty(): array
    {
        return Cache::remember('mapa-clima:timeline-data', now()->addMinute(), function (): array {
            $since = now()->subHours(24);
            $until = now()->endOfMinute();
            $distritos = Distrito::query()->get(['id', 'nome'])->keyBy('id');

            $timestamps = DB::table('temperatura_registrada')
                ->where('timestamp', '>=', $since)
                ->where('timestamp', '<=', $until)
                ->orderBy('timestamp')
                ->pluck('timestamp')
                ->map(fn ($timestamp) => Carbon::parse($timestamp)->format('Y-m-d H:i'))
                ->unique()
                ->groupBy(fn (string $timestamp) => substr($timestamp, 0, 13))
                ->map(fn ($hour) => $hour->last())
                ->values()
                ->take(-24)
                ->values();

            if ($timestamps->isEmpty()) {
                return [];
            }

            $sources = [
                'temperatura' => ['temperatura_registrada', ['temperatura', 'sensacao_termica']],
                'vento' => ['vento_registrado', ['velocidade', 'direcao']],
                'chuva' => ['chuva_registrada', ['precipitacao_mm']],
                'radiacao' => ['radiacao_solar', ['radiacao_w_m2']],
                'uv' => ['indice_uv', ['uv']],
                'particulas' => ['particulas_ar', ['pm25']],
                'pressao' => ['pressao_atmosferica', ['pressao_hpa']],
            ];

            $readings = [];
            foreach ($sources as $metric => [$table, $columns]) {
                $rows = DB::table($table)
                    ->where('timestamp', '>=', $since)
                    ->where('timestamp', '<=', $until)
                    ->get(array_merge(['distrito_id', 'timestamp', 'origem_dado', 'fonte_dado'], $columns));

                foreach ($rows as $row) {
                    $stamp = Carbon::parse($row->timestamp)->format('Y-m-d H:i');
                    $readings[$metric][$row->distrito_id][$stamp] = $row;
                }
            }

            return $timestamps->map(function (string $stamp) use ($distritos, $readings) {
                $records = $distritos->map(function (Distrito $d) use ($stamp, $readings) {
                    $temp = $readings['temperatura'][$d->id][$stamp] ?? null;
                    $vento = $readings['vento'][$d->id][$stamp] ?? null;
                    $chuva = $readings['chuva'][$d->id][$stamp] ?? null;
                    $rad = $readings['radiacao'][$d->id][$stamp] ?? null;
                    $uv = $readings['uv'][$d->id][$stamp] ?? null;
                    $particulas = $readings['particulas'][$d->id][$stamp] ?? null;
                    $pressao = $readings['pressao'][$d->id][$stamp] ?? null;

                    return [
                        'id' => $d->id,
                        'temperatura' => $temp?->temperatura,
                        'sensacao_termica' => $temp?->sensacao_termica,
                        'vento_velocidade' => $vento?->velocidade,
                        'vento_direcao' => $vento?->direcao,
                        'radiacao' => $rad?->radiacao_w_m2,
                        'chuva' => $chuva?->precipitacao_mm,
                        'uv' => $uv?->uv,
                        'particulas' => $particulas?->pm25,
                        'pressao' => $pressao?->pressao_hpa,
                        'timestamp' => Carbon::createFromFormat('Y-m-d H:i', $stamp)->format('d/m/Y H:i'),
                        'origem_dado' => $temp?->origem_dado ?? $vento?->origem_dado ?? $chuva?->origem_dado ?? 'desconhecido',
                        'fonte_dado' => $temp?->fonte_dado ?? $vento?->fonte_dado ?? $chuva?->fonte_dado,
                    ];
                })->values()->all();

                return [
                    'timestamp' => $stamp,
                    'label' => Carbon::createFromFormat('Y-m-d H:i', $stamp)->format('d/m H:i'),
                    'data' => $records,
                ];
            })->all();
        });
    }
}
