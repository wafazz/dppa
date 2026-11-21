<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RegistrationSwitch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationSwitchController extends Controller
{
    public function show(RegistrationSwitch $switch): JsonResponse
    {
        return response()->json(['data' => ['open' => $switch->isOpen()]]);
    }

    public function update(Request $request, RegistrationSwitch $switch): JsonResponse
    {
        $validated = $request->validate(['open' => ['required', 'boolean']]);

        $switch->set((bool) $validated['open'], $request->user()->id);

        return $this->show($switch);
    }
}
