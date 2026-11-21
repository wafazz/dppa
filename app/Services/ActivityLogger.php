<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit trail. The acting account comes from the signed-in user unless one is given
 * (sign-in attempts, for example, have no account yet). Secrets are never passed in here.
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $event,
        ?string $description = null,
        ?User $actor = null,
        ?Model $subject = null,
        array $properties = [],
    ): ActivityLog {
        $request = request();
        $actor ??= Auth::user();

        return ActivityLog::query()->create([
            'occurred_at' => Carbon::now(),
            'user_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description === null ? null : mb_substr($description, 0, 500),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => app()->runningInConsole() ? null : $request->ip(),
            'user_agent' => app()->runningInConsole() ? null : mb_substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
