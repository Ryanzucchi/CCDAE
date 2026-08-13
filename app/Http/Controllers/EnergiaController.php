<?php

namespace App\Http\Controllers;

use App\Services\Analytics\EnergyAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class EnergiaController extends Controller
{
    public function analytics(EnergyAnalyticsService $analytics): JsonResponse
    {
        $seconds = (int) config('data_sources.analytics_cache_seconds', 300);
        $collections = Cache::remember('analytics:energia:v3', $seconds, $analytics->build(...));
        return response()->json($collections)->header('Cache-Control', "private, max-age={$seconds}");
    }
}
