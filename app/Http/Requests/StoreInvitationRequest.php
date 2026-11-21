<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvitationRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to users who may invite.
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
            'note' => ['nullable', 'string', 'max:255'],
            'recipient_email' => ['nullable', 'email:rfc', 'max:190'],
            'expires_in_days' => ['required', 'integer', 'between:1,30'],
            'max_uses' => ['required', 'integer', 'between:1,20'],
        ];
    }
}
