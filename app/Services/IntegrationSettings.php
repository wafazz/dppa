<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Reads and writes integration settings. API keys are encrypted with the application key and are
 * never returned to the browser after they are saved.
 */
class IntegrationSettings
{
    public const SECRETS = ['resend.api_key', 'onsend.api_key'];

    public const PLAIN = ['resend.from_email', 'resend.from_name', 'onsend.base_url', 'onsend.sender_id'];

    public function get(string $key): ?string
    {
        $setting = AppSetting::query()->find($key);

        if ($setting === null || $setting->value === null) {
            return null;
        }

        return $setting->is_secret ? Crypt::decryptString($setting->value) : $setting->value;
    }

    public function has(string $key): bool
    {
        return AppSetting::query()->whereKey($key)->whereNotNull('value')->exists();
    }

    /**
     * Store a value. A null or empty secret keeps the existing key; a plain value may be cleared.
     */
    public function put(string $key, ?string $value, ?int $userId): void
    {
        $isSecret = in_array($key, self::SECRETS, true);
        $value = $value === null ? null : trim($value);

        if ($isSecret && ($value === null || $value === '')) {
            return;
        }

        // Changing the Resend key or sender means the last successful test no longer applies.
        if (in_array($key, ['resend.api_key', 'resend.from_email'], true)) {
            AppSetting::query()->whereKey('resend.verified_at')->delete();
        }

        AppSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => ($value === null || $value === '') ? null : ($isSecret ? Crypt::encryptString($value) : $value),
                'is_secret' => $isSecret,
                'updated_by_user_id' => $userId,
            ],
        );
    }

    /**
     * A channel is connected when its settings are complete and a test message has been sent successfully.
     * WhatsApp (OnSend) has no sending path yet, so it stays disconnected until one is built.
     */
    public function isConnected(string $channel): bool
    {
        return match ($channel) {
            'resend' => $this->has('resend.api_key') && $this->has('resend.from_email') && $this->has('resend.verified_at'),
            'onsend' => $this->has('onsend.api_key') && $this->has('onsend.base_url') && $this->has('onsend.sender_id') && $this->has('onsend.verified_at'),
            default => false,
        };
    }

    /**
     * Records a successful test so the channel counts as connected.
     */
    public function markVerified(string $channel, ?int $userId): void
    {
        $this->put($channel.'.verified_at', now()->toIso8601String(), $userId);
    }

    /**
     * Non-secret values and whether each secret is saved. Secrets themselves are never included.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'resend' => [
                'from_email' => $this->get('resend.from_email'),
                'from_name' => $this->get('resend.from_name'),
                'has_api_key' => $this->has('resend.api_key'),
                'connected' => $this->isConnected('resend'),
            ],
            'onsend' => [
                'base_url' => $this->get('onsend.base_url'),
                'sender_id' => $this->get('onsend.sender_id'),
                'has_api_key' => $this->has('onsend.api_key'),
                'connected' => $this->isConnected('onsend'),
            ],
        ];
    }
}
