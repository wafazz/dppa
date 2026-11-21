<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Personal details. Email is not part of this request, so any email sent by the browser is ignored.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address_line1' => ['required', 'string', 'max:190'],
            'address_line2' => ['nullable', 'string', 'max:190'],
            'postcode' => ['nullable', 'digits:5'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', Rule::in(config('membership.states'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'postcode.digits' => __('app.validation.postcode_format'),
            'state.in' => __('app.validation.state_invalid'),
        ];
    }
}
