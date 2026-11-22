<?php

namespace App\Http\Requests;

use App\Models\FeeType;
use App\Services\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeTypeRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to administrators.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $feeType = $this->route('feeType');

        return [
            'code' => ['required', 'string', 'max:40', 'alpha_dash', Rule::unique('fee_types', 'code')->ignore($feeType?->id)],
            'name_bm' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'string', 'max:14', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Money::toCents((string) $value) === null) {
                    $fail(__('app.billing.error_amount'));
                }
            }],
            'frequency' => ['required', Rule::in(FeeType::FREQUENCIES)],
            'business_category_id' => ['nullable', 'integer', Rule::exists('business_categories', 'id')],
            'due_days' => ['required', 'integer', 'between:1,365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,999'],
        ];
    }
}
