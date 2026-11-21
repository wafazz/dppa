<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

/**
 * One entry in the audit trail. Entries are only ever added, never edited or deleted.
 */
#[Fillable(['occurred_at', 'user_id', 'event', 'subject_type', 'subject_id', 'description', 'properties', 'ip_address', 'user_agent'])]
class ActivityLog extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Management accounts (superadmin and admin) see every entry. Everyone else sees only their own.
     *
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->account_type === AccountType::Superadmin || $viewer->account_type === AccountType::Admin) {
            return $query;
        }

        return $query->where('user_id', $viewer->id);
    }

    /**
     * The first part of the event name, used to filter the page: "member" for "member.updated".
     */
    public function category(): string
    {
        return explode('.', $this->event)[0];
    }

    /**
     * A readable label. Named events have their own text; model events are built from the subject and the action.
     */
    public function label(): string
    {
        $key = 'app.activity.events.'.$this->event;

        if (Lang::has($key)) {
            return __($key);
        }

        [$subject, $action] = array_pad(explode('.', $this->event, 2), 2, '');

        if (Lang::has('app.activity.subjects.'.$subject) && Lang::has('app.activity.actions.'.$action)) {
            return __('app.activity.subjects.'.$subject).' · '.__('app.activity.actions.'.$action);
        }

        return $this->event;
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'properties' => 'array',
        ];
    }
}
