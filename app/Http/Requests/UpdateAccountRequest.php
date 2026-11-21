<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Route middleware and AccountService restrict who may change which account.
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
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:12'],
            'is_active' => ['required', 'boolean'],
            'role' => ['nullable', Rule::enum(UserRole::class)],
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
        ];
    }
}
