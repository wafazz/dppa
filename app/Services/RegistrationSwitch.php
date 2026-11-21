<?php

namespace App\Services;

use App\Models\AppSetting;

/**
 * Global on/off switch for public registration. When it is off, every invitation link is refused.
 * Registration is open unless it has been switched off.
 */
class RegistrationSwitch
{
    public const KEY = 'registration.open';

    public function isOpen(): bool
    {
        $setting = AppSetting::query()->find(self::KEY);

        return $setting === null || $setting->value !== 'false';
    }

    public function set(bool $open, ?int $userId): void
    {
        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => $open ? 'true' : 'false', 'is_secret' => false, 'updated_by_user_id' => $userId],
        );
    }
}
