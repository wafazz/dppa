<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class VoteRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => ['required', 'date', 'after:now'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*' => ['required', 'string', 'max:190', 'distinct'],
            'voter_ids' => ['present', 'array'],
            'voter_ids.*' => ['integer'],
            'account_position_ids' => ['present', 'array'],
            'account_position_ids.*' => ['integer', 'exists:account_positions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (count($this->input('voter_ids', [])) === 0 && count($this->input('account_position_ids', [])) === 0) {
                $validator->errors()->add('voter_ids', __('app.votes.error_no_voters'));
            }

            $opens = $this->input('opens_at');
            $closes = $this->input('closes_at');

            if ($opens && $closes && strtotime((string) $closes) <= strtotime((string) $opens)) {
                $validator->errors()->add('closes_at', __('app.votes.error_close_before_open'));
            }
        });
    }
}
