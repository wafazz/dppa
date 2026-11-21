<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountPositionRequest;
use App\Http\Requests\UpdateAccountPositionRequest;
use App\Http\Resources\AccountPositionResource;
use App\Models\AccountPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountPositionController extends Controller
{
    /**
     * Management positions ordered by rank (level 1 first).
     */
    public function index(): AnonymousResourceCollection
    {
        return AccountPositionResource::collection(AccountPosition::query()->orderBy('level')->get());
    }

    public function store(StoreAccountPositionRequest $request): JsonResponse|JsonResource
    {
        $position = AccountPosition::query()->create($request->validated());

        return (new AccountPositionResource($position))->response()->setStatusCode(201);
    }

    public function update(UpdateAccountPositionRequest $request, AccountPosition $accountPosition): JsonResource
    {
        $accountPosition->update($request->validated());

        return new AccountPositionResource($accountPosition);
    }
}
