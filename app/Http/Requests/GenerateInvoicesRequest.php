<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoicesRequest extends FormRequest
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
            'fee_type_id' => ['required', 'integer', Rule::exists('fee_types', 'id')->where('is_active', true)],
            // 2027 for a membership year or a whole year of months, 2027-03 for one month. Empty for lifetime fees.
            'period_type' => ['required', 'string', 'in:monthly,yearly,lifetime'],
            'period' => ['nullable', 'string', 'regex:/^\d{4}(-(0[1-9]|1[0-2]))?$/'],
        ];
    }
}
