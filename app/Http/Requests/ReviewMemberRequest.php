<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReviewMemberRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to committee roles.
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
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'note' => ['nullable', 'string', 'max:20000'],
        ];
    }

    /**
     * A rejection must always record the reason.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('status') !== MemberStatus::Rejected->value) {
                    return;
                }

                if (! app(HtmlSanitizer::class)->hasText((string) $this->input('note'))) {
                    $validator->errors()->add('note', __('app.validation.reason_required'));
                }
            },
        ];
    }
}
