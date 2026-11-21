<?php

namespace App\Services;

use App\Models\KnownDevice;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Remembers the browsers each account signs in from. A sign-in from a browser the account has not used before
 * is reported by e-mail, unless it is the account's first browser.
 */
class DeviceAlerts
{
    public function __construct(private EmailNotices $notices) {}

    public function check(User $user, Request $request): void
    {
        $hash = hash('sha256', (string) $request->userAgent());

        $known = KnownDevice::query()
            ->where('user_id', $user->id)
            ->where('device_hash', $hash)
            ->first();

        if ($known !== null) {
            $known->forceFill(['last_seen_at' => now()])->save();

            return;
        }

        $hadDevices = KnownDevice::query()->where('user_id', $user->id)->exists();

        KnownDevice::query()->create([
            'user_id' => $user->id,
            'device_hash' => $hash,
            'last_seen_at' => now(),
        ]);

        if (! $hadDevices) {
            return;
        }

        $this->notices->deliver(
            'new_device_sign_in',
            (string) $user->email,
            [
                'name' => $user->name,
                'time' => now()->translatedFormat('j M Y, H:i'),
                'ip' => (string) $request->ip(),
            ],
            route('password.request'),
        );
    }
}
