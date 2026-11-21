<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function show(Branding $branding): JsonResponse
    {
        return response()->json(['data' => ['logo_url' => $branding->logoUrl()]]);
    }

    /**
     * Accepts the logo the browser prepared on a canvas (PNG, at most 512 pixels on each side).
     */
    public function store(Request $request, Branding $branding): JsonResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=32,min_height=32,max_width=512,max_height=512'],
        ]);

        $branding->storeLogo($validated['logo']->getContent(), $request->user()->id);

        return $this->show($branding);
    }

    public function destroy(Branding $branding): JsonResponse
    {
        $branding->removeLogo();

        return $this->show($branding);
    }
}
