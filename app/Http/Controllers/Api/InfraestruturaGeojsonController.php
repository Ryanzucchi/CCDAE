<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Infrastructure\InfrastructureQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfraestruturaGeojsonController extends Controller
{
    public function __invoke(Request $request, InfrastructureQueryService $service): JsonResponse
    {
        $districtId = $request->filled('distrito_id') ? (int) $request->query('distrito_id') : null;
        $includeFeatures = $request->boolean('include_features', true);
        $energyOnly = $request->boolean('energy_only');
        $energyOverview = $energyOnly && $request->boolean('energy_overview') && ! $districtId;

        $data = $service->query($districtId, $includeFeatures, $energyOnly, $energyOverview);

        return response()->json($data);
    }
}
