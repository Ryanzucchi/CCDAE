<?php

namespace App\Http\Controllers;

use App\Services\Analytics\EnergyAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EnergiaController extends Controller
{
    public function analytics(Request $request, EnergyAnalyticsService $analytics): JsonResponse
    {
        $seconds = (int) config('data_sources.analytics_cache_seconds', 300);
        $type = $request->string('tipo')->toString();
        $type = in_array($type, ['densidade', 'consumo', 'populacao'], true) ? $type : null;
        $collections = Cache::remember("analytics:energia:v4:".($type ?? 'todos'), $seconds, fn () => $analytics->build($type));

        return response()->json($collections)->header('Cache-Control', "private, max-age={$seconds}");
    }
}
