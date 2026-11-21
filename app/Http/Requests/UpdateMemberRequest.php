<?php

namespace App\Http\Requests;

use App\Models\BusinessCategory;
use App\Models\Member;
use App\Services\DuplicateMemberFinder;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMemberRequest extends FormRequest
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
            'applicant_name' => ['required', 'string', 'max:150'],
            'business_name' => ['required', 'string', 'max:200'],
            'business_category_id' => [
                'required',
                Rule::exists((new BusinessCategory)->getTable(), 'id'),
            ],
            'business_address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{6,19}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'ssm_no' => ['nullable', 'string', 'max:50'],
            'business_description' => ['required', 'string', 'max:20000'],
            'business_needs' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('app.validation.phone_invalid'),
        ];
    }

    /**
     * Add checks that need the database or rich-text content.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var Member $member */
                $member = $this->route('member');

                foreach (['business_description', 'business_needs'] as $field) {
                    if (! $validator->errors()->has($field) && ! app(HtmlSanitizer::class)->hasText((string) $this->input($field))) {
                        $validator->errors()->add($field, __('app.validation.field_required'));
                    }
                }

                $duplicate = app(DuplicateMemberFinder::class)->ssmConflict($this->input('ssm_no'), $member->id);

                if ($duplicate !== null) {
                    $validator->errors()->add('ssm_no', __('app.validation.ssm_taken_admin', ['id' => $duplicate->id]));
                }
            },
        ];
    }
}
