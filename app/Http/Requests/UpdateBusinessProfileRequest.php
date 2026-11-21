<?php

namespace App\Http\Requests;

use App\Models\BusinessCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessProfileRequest extends FormRequest
{
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
            'business_name' => ['nullable', 'string', 'max:190'],
            'ssm_no' => ['nullable', 'string', 'max:60'],
            'business_category_id' => ['nullable', 'integer', Rule::exists(BusinessCategory::class, 'id')],
            'business_phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
