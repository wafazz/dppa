<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessCategoryRequest;
use App\Http\Requests\UpdateBusinessCategoryRequest;
use App\Http\Resources\BusinessCategoryResource;
use App\Models\BusinessCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessCategoryController extends Controller
{
    /**
     * List every business category, including inactive ones, for the admin page.
     */
    public function index(): AnonymousResourceCollection
    {
        return BusinessCategoryResource::collection(
            BusinessCategory::query()->orderBy('sort_order')->orderBy('name_bm')->get(),
        );
    }

    /**
     * Add a business category.
     */
    public function store(StoreBusinessCategoryRequest $request): JsonResponse
    {
        $category = BusinessCategory::query()->create($request->validated());

        return (new BusinessCategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * Edit a business category. Categories are deactivated rather than deleted to protect existing records.
     */
    public function update(UpdateBusinessCategoryRequest $request, BusinessCategory $businessCategory): JsonResource
    {
        $businessCategory->update($request->validated());

        return new BusinessCategoryResource($businessCategory);
    }
}
