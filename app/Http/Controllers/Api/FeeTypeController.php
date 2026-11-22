<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FeeTypeRequest;
use App\Models\FeeType;
use App\Services\ActivityLogger;
use App\Services\Money;
use Illuminate\Http\JsonResponse;

class FeeTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $feeTypes = FeeType::query()
            ->with('businessCategory')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (FeeType $feeType): array => $this->payload($feeType));

        return response()->json(['data' => $feeTypes]);
    }

    public function store(FeeTypeRequest $request, ActivityLogger $activity): JsonResponse
    {
        $feeType = FeeType::query()->create($this->attributes($request->validated()));

        $activity->record('fee_type.saved', $feeType->name_bm, $request->user(), $feeType, ['action' => 'created']);

        return response()->json(['data' => $this->payload($feeType->refresh()->load('businessCategory'))], 201);
    }

    public function update(FeeTypeRequest $request, FeeType $feeType, ActivityLogger $activity): JsonResponse
    {
        $feeType->update($this->attributes($request->validated()));

        $activity->record('fee_type.saved', $feeType->name_bm, $request->user(), $feeType, ['action' => 'updated']);

        return response()->json(['data' => $this->payload($feeType->refresh()->load('businessCategory'))]);
    }

    /**
     * Amounts arrive as ringgit text and are stored in sen. Changing a fee never changes invoices already issued.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'code' => strtolower(trim((string) $validated['code'])),
            'name_bm' => trim((string) $validated['name_bm']),
            'name_en' => isset($validated['name_en']) && trim((string) $validated['name_en']) !== '' ? trim((string) $validated['name_en']) : null,
            'description' => isset($validated['description']) && trim((string) $validated['description']) !== '' ? trim((string) $validated['description']) : null,
            'amount_cents' => Money::toCents((string) $validated['amount']),
            'frequency' => $validated['frequency'],
            'business_category_id' => $validated['business_category_id'] ?? null,
            'due_days' => (int) $validated['due_days'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FeeType $feeType): array
    {
        return [
            'id' => $feeType->id,
            'code' => $feeType->code,
            'name_bm' => $feeType->name_bm,
            'name_en' => $feeType->name_en,
            'description' => $feeType->description,
            'amount' => number_format($feeType->amount_cents / 100, 2, '.', ''),
            'amount_label' => Money::format($feeType->amount_cents),
            'frequency' => $feeType->frequency,
            'business_category_id' => $feeType->business_category_id,
            'business_category' => $feeType->businessCategory?->name_bm,
            'due_days' => $feeType->due_days,
            'is_active' => (bool) $feeType->is_active,
            'sort_order' => $feeType->sort_order,
        ];
    }
}
