<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Display pictures for committee accounts. Each account has one square PNG on the public disk,
 * and the path is kept on the user row. Without a picture the layout shows the account initial.
 */
class Avatar
{
    public const DISK = 'public';

    public function url(User $user): ?string
    {
        if ($user->avatar_path === null) {
            return null;
        }

        return Storage::disk(self::DISK)->url($user->avatar_path).'?v='.$user->updated_at?->timestamp;
    }

    public function store(User $user, string $contents): void
    {
        $path = 'avatars/user-'.$user->id.'.png';

        Storage::disk(self::DISK)->put($path, $contents);

        $user->forceFill(['avatar_path' => $path])->save();
    }

    public function remove(User $user): void
    {
        if ($user->avatar_path !== null) {
            Storage::disk(self::DISK)->delete($user->avatar_path);
        }

        $user->forceFill(['avatar_path' => null])->save();
    }
}
