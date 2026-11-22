<?php

namespace App\Http\Requests;

use App\Services\Money;
use App\Services\PaymentSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPaymentRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to committee accounts.
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
        return [
            'amount' => ['required', 'string', 'max:14', function (string $attribute, mixed $value, \Closure $fail): void {
                if (Money::toCents((string) $value) === null) {
                    $fail(__('app.billing.error_amount'));
                }
            }],
            'method' => ['required', Rule::in(PaymentSettings::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
