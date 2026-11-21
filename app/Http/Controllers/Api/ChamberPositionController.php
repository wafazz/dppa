<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChamberPositionRequest;
use App\Http\Requests\UpdateChamberPositionRequest;
use App\Http\Resources\ChamberPositionResource;
use App\Models\ChamberPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class ChamberPositionController extends Controller
{
    /**
     * List every chamber position in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        return ChamberPositionResource::collection(
            ChamberPosition::query()->orderBy('sort_order')->orderBy('id')->get(),
        );
    }

    /**
     * Add a chamber position name.
     */
    public function store(StoreChamberPositionRequest $request): JsonResponse
    {
        $position = ChamberPosition::query()->create($request->validated());

        return (new ChamberPositionResource($position))->response()->setStatusCode(201);
    }

    /**
     * Edit a chamber position name or its display order.
     */
    public function update(UpdateChamberPositionRequest $request, ChamberPosition $chamberPosition): JsonResource
    {
        $chamberPosition->update($request->validated());

        return new ChamberPositionResource($chamberPosition);
    }
}
