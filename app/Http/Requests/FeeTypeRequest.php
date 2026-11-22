<?php

namespace App\Http\Requests;

use App\Services\Money;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'monthly_amount' => ['nullable', 'string', 'max:14', $this->amountRule()],
            'yearly_amount' => ['nullable', 'string', 'max:14', $this->amountRule()],
            'lifetime_amount' => ['nullable', 'string', 'max:14', $this->amountRule()],
            'business_category_id' => ['nullable', 'integer', Rule::exists('business_categories', 'id')],
            'due_days' => ['required', 'integer', 'between:1,365'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,999'],
        ];
    }

    /**
     * A fee must offer at least one period.
     *
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $offered = collect(['monthly_amount', 'yearly_amount', 'lifetime_amount'])
                    ->filter(fn (string $field): bool => trim((string) $this->input($field)) !== '');

                if ($offered->isEmpty()) {
                    $validator->errors()->add('yearly_amount', __('app.billing.error_no_period'));
                }
            },
        ];
    }

    /**
     * Every filled price must be a positive amount.
     */
    private function amountRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (trim((string) $value) !== '' && Money::toCents((string) $value) === null) {
                $fail(__('app.billing.error_amount'));
            }
        };
    }
}
