<?php

namespace App\Http\Requests;

use App\Models\ChamberPosition;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionAppointmentRequest extends FormRequest
{
    /**
     * Route middleware restricts appointments to committee roles.
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
            'member_id' => [
                'required',
                Rule::exists((new Member)->getTable(), 'id')->whereNull('deleted_at'),
            ],
            'chamber_position_id' => [
                'required',
                Rule::exists((new ChamberPosition)->getTable(), 'id')->where('is_active', true),
            ],
            'term_start' => ['required', 'date'],
            'term_end' => ['nullable', 'date', 'after_or_equal:term_start'],
            'is_current' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'term_end.after_or_equal' => __('app.validation.term_end_order'),
        ];
    }
}
