<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Dashboard figures for the signed-in account.
     */
    public function show(Request $request, DashboardService $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'in:6,12'],
        ]);

        return response()->json([
            'data' => $dashboard->summary($request->user(), (int) ($validated['months'] ?? 6)),
        ]);
    }
}
