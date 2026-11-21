<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpgradeMemberRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to management accounts.
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
            'account_type' => ['required', Rule::in(['admin', 'staff'])],
            'role' => ['required_if:account_type,staff', 'nullable', Rule::enum(UserRole::class)],
            'can_invite' => ['sometimes', 'boolean'],
            'account_position_id' => ['nullable', 'integer', Rule::exists('account_positions', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required_if' => __('app.validation.role_required'),
        ];
    }
}
