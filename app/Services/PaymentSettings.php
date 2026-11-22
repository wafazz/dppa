<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Chamber payment details and billing rules. Kept as one JSON value in app_settings, so an administrator can
 * change them in the app without a code change or a deployment.
 */
class PaymentSettings
{
    public const KEY = 'payments.settings';

    /** Payment methods the committee can record. Each one can be switched off. */
    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'online'];

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'bank_name' => '',
            'account_name' => '',
            'account_number' => '',
            'enabled_methods' => ['cash', 'bank_transfer', 'cheque'],
            'instructions_bm' => '',
            'instructions_en' => '',
            'receipt_footer' => '',
            'invoice_prefix' => 'INV',
            'receipt_prefix' => 'RCP',
            'currency_symbol' => 'RM',
            'auto_invoice_on_approval' => false,
            'send_invoice_emails' => true,
            'reminder_after_days' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $saved = AppSetting::query()->find(self::KEY);
        $values = $saved?->value !== null ? json_decode((string) $saved->value, true) : null;

        return array_merge($this->defaults(), is_array($values) ? $values : []);
    }

    /**
     * @return array<int, string>
     */
    public function enabledMethods(): array
    {
        return array_values(array_intersect(self::METHODS, (array) $this->all()['enabled_methods']));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function save(array $values, ?int $userId): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            [
                'value' => json_encode($values, JSON_UNESCAPED_UNICODE),
                'is_secret' => false,
                'updated_by_user_id' => $userId,
            ],
        );
    }
}
