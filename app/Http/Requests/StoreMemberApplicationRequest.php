<?php

namespace App\Http\Requests;

use App\Models\BusinessCategory;
use App\Services\DuplicateMemberFinder;
use App\Services\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMemberApplicationRequest extends FormRequest
{
    /**
     * Public registration is open to any visitor. Rate limiting is applied on the route.
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
                Rule::exists((new BusinessCategory)->getTable(), 'id')->where('is_active', true),
            ],
            'business_address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{6,19}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'ssm_no' => ['nullable', 'string', 'max:50'],
            'business_description' => ['required', 'string', 'max:20000'],
            'business_needs' => ['required', 'string', 'max:20000'],
            'consent' => ['accepted'],
            'announcement_consent' => ['nullable', 'boolean'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'Permohonan tidak dapat diproses.',
            'consent.accepted' => 'Sila setujui penyimpanan dan penggunaan maklumat untuk pentadbiran keahlian.',
            'phone.regex' => 'Sila masukkan nombor telefon yang sah, contohnya 012-3456789.',
        ];
    }

    /**
     * Add checks that need the database or rich-text content.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->ensureRichTextHasContent($validator, 'business_description');
                $this->ensureRichTextHasContent($validator, 'business_needs');

                $duplicate = app(DuplicateMemberFinder::class)->ssmConflict($this->input('ssm_no'));

                if ($duplicate !== null) {
                    $validator->errors()->add('ssm_no', 'Permohonan tidak dapat diproses. Sila hubungi pejabat Dewan untuk maklumat lanjut.');
                }
            },
        ];
    }

    /**
     * Reject rich-text fields that contain only formatting or whitespace.
     */
    private function ensureRichTextHasContent(Validator $validator, string $field): void
    {
        if ($validator->errors()->has($field)) {
            return;
        }

        if (! app(HtmlSanitizer::class)->hasText((string) $this->input($field))) {
            $validator->errors()->add($field, 'Medan ini wajib diisi.');
        }
    }
}
