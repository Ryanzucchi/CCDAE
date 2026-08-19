<?php

namespace App\Http\Controllers;

use App\Services\Traffic\TrafficIntensityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransitoController extends Controller
{
    public function intensity(Request $request, TrafficIntensityService $service): JsonResponse
    {
        try {
            $periodEnd = $request->filled('fim') ? Carbon::parse($request->string('fim')->toString()) : now();
            $periodStart = $request->filled('inicio') ? Carbon::parse($request->string('inicio')->toString()) : $periodEnd->copy()->subHours(24);
        } catch (\Throwable) {
            return response()->json(['message' => 'Período informado é inválido.'], 422);
        }

        if ($periodStart->greaterThanOrEqualTo($periodEnd) || $periodStart->diffInDays($periodEnd) > 31) {
            return response()->json(['message' => 'O período deve ter até 31 dias e a data inicial deve ser anterior à final.'], 422);
        }

        $requestedType = $request->filled('tipo') ? $request->string('tipo')->toString() : null;
        $result = $service->calculate($periodStart, $periodEnd, $requestedType);

        return response()->json($result);
    }
}
