<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')],
            'account_type' => ['required', Rule::in(['admin', 'staff'])],
            'send_setup_link' => ['sometimes', 'boolean'],
            'password' => [Rule::requiredIf(fn (): bool => ! $this->boolean('send_setup_link')), 'string', 'min:12'],
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
            'password.min' => __('app.validation.password_min'),
            'email.unique' => __('app.validation.email_taken'),
            'role.required_if' => __('app.validation.role_required'),
        ];
    }
}
