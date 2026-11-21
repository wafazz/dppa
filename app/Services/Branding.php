<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Storage;

/**
 * The system logo shown in the committee area. Uploads are stored on the public disk and the
 * path is kept in app_settings. Without an uploaded logo the text name is shown instead.
 */
class Branding
{
    public const KEY = 'branding.logo_path';

    public const DISK = 'public';

    public const LOGO_FILE = 'branding/logo.png';

    public function logoUrl(): ?string
    {
        $setting = AppSetting::query()->find(self::KEY);

        if ($setting === null || $setting->value === null) {
            return null;
        }

        return Storage::disk(self::DISK)->url($setting->value).'?v='.$setting->updated_at?->timestamp;
    }

    public function storeLogo(string $contents, ?int $userId): void
    {
        Storage::disk(self::DISK)->put(self::LOGO_FILE, $contents);

        AppSetting::query()->updateOrCreate(
            ['key' => self::KEY],
            ['value' => self::LOGO_FILE, 'is_secret' => false, 'updated_by_user_id' => $userId],
        );
    }

    public function removeLogo(): void
    {
        Storage::disk(self::DISK)->delete(self::LOGO_FILE);

        AppSetting::query()->whereKey(self::KEY)->delete();
    }
}
