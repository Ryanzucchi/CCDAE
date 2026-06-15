<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class DataCollectionChart extends ChartWidget
{
    protected ?string $heading = 'Volume de Coleta (Últimos 7 dias)';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected ?string $maxHeight = '200px';

    protected function getData(): array
    {
        // Pega os logs da tabela principal de temperatura
        // Multiplicaremos por 7, pois 1 coleta salva em 7 tabelas simultaneamente
        $data = DB::table('temperatura_registrada')
            ->select(DB::raw('date(timestamp) as date'), DB::raw('count(*) as total'))
            ->where('timestamp', '>=', now()->subDays(6)->startOfDay())
            ->groupBy(DB::raw('date(timestamp)'))
            ->orderBy('date', 'asc')
            ->get()
            ->pluck('total', 'date')
            ->toArray();

        $labels = [];
        $dataset = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d/m');
            // Multiplica por 7 para representar o volume massivo (temperatura, chuva, vento, etc)
            $dataset[] = (int) (($data[$day] ?? 0) * 7);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jobs de Coleta Executados',
                    'data' => $dataset,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
