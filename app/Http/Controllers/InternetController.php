<?php

namespace App\Http\Controllers;

use App\Services\Analytics\InternetAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class InternetController extends Controller
{
    public function analytics(InternetAnalyticsService $analytics): JsonResponse
    {
        $seconds = (int) config('data_sources.analytics_cache_seconds', 300);
        $collections = Cache::remember('analytics:internet:v3', $seconds, $analytics->build(...));
        return response()->json($collections)->header('Cache-Control', "private, max-age={$seconds}");
    }
}
