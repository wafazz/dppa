<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentSettingsRequest;
use App\Services\ActivityLogger;
use App\Services\PaymentSettings;
use Illuminate\Http\JsonResponse;

class PaymentSettingsController extends Controller
{
    public function show(PaymentSettings $settings): JsonResponse
    {
        return response()->json(['data' => $settings->all() + ['methods' => PaymentSettings::METHODS]]);
    }

    public function update(PaymentSettingsRequest $request, PaymentSettings $settings, ActivityLogger $activity): JsonResponse
    {
        $validated = $request->validated();

        $values = [
            'bank_name' => trim((string) ($validated['bank_name'] ?? '')),
            'account_name' => trim((string) ($validated['account_name'] ?? '')),
            'account_number' => trim((string) ($validated['account_number'] ?? '')),
            'enabled_methods' => array_values(array_unique($validated['enabled_methods'])),
            'instructions_bm' => trim((string) ($validated['instructions_bm'] ?? '')),
            'instructions_en' => trim((string) ($validated['instructions_en'] ?? '')),
            'receipt_footer' => trim((string) ($validated['receipt_footer'] ?? '')),
            'invoice_prefix' => strtoupper($validated['invoice_prefix']),
            'receipt_prefix' => strtoupper($validated['receipt_prefix']),
            'currency_symbol' => trim($validated['currency_symbol']),
            'auto_invoice_on_approval' => (bool) $validated['auto_invoice_on_approval'],
            'send_invoice_emails' => (bool) $validated['send_invoice_emails'],
            'reminder_after_days' => (int) $validated['reminder_after_days'],
        ];

        $settings->save($values, $request->user()->id);

        $activity->record('payment_settings.saved', 'Payment settings', $request->user(), null, ['fields' => array_keys($values)]);

        return response()->json(['data' => $settings->all() + ['methods' => PaymentSettings::METHODS]]);
    }
}
