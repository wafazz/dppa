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
     * Each period price is ringgit text stored in sen; an empty price means that period is not offered.
     * Changing a price never changes invoices already issued.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            'code' => strtolower(trim((string) $validated['code'])),
            'name_bm' => trim((string) $validated['name_bm']),
            'name_en' => $this->text($validated['name_en'] ?? null),
            'description' => $this->text($validated['description'] ?? null),
            'monthly_cents' => $this->cents($validated['monthly_amount'] ?? null),
            'yearly_cents' => $this->cents($validated['yearly_amount'] ?? null),
            'lifetime_cents' => $this->cents($validated['lifetime_amount'] ?? null),
            'business_category_id' => $validated['business_category_id'] ?? null,
            'due_days' => (int) $validated['due_days'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];
    }

    private function cents(?string $amount): ?int
    {
        return $amount === null || trim($amount) === '' ? null : Money::toCents($amount);
    }

    private function text(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FeeType $feeType): array
    {
        $prices = [];

        foreach (FeeType::PERIODS as $period) {
            $cents = $feeType->priceFor($period);

            $prices[$period] = [
                'amount' => $cents === null ? null : number_format($cents / 100, 2, '.', ''),
                'label' => $cents === null ? null : Money::format($cents),
            ];
        }

        return [
            'id' => $feeType->id,
            'code' => $feeType->code,
            'name_bm' => $feeType->name_bm,
            'name_en' => $feeType->name_en,
            'description' => $feeType->description,
            'prices' => $prices,
            'periods' => $feeType->offeredPeriods(),
            'business_category_id' => $feeType->business_category_id,
            'business_category' => $feeType->businessCategory?->name_bm,
            'due_days' => $feeType->due_days,
            'is_active' => (bool) $feeType->is_active,
            'sort_order' => $feeType->sort_order,
        ];
    }
}
