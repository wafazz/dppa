<?php

namespace App\Http\Requests;

use App\Services\PaymentSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentSettingsRequest extends FormRequest
{
    /**
     * Route middleware restricts this request to administrators.
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
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:40'],
            'enabled_methods' => ['present', 'array'],
            'enabled_methods.*' => [Rule::in(PaymentSettings::METHODS)],
            'instructions_bm' => ['nullable', 'string', 'max:2000'],
            'instructions_en' => ['nullable', 'string', 'max:2000'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'invoice_prefix' => ['required', 'string', 'alpha_dash', 'max:10'],
            'receipt_prefix' => ['required', 'string', 'alpha_dash', 'max:10'],
            'currency_symbol' => ['required', 'string', 'max:6'],
            'auto_invoice_on_approval' => ['required', 'boolean'],
            'send_invoice_emails' => ['required', 'boolean'],
            'reminder_after_days' => ['required', 'integer', 'between:0,90'],
        ];
    }
}
