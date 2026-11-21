<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vote set up by someone allowed to create votes. Its status follows from its times and whether it was closed or cancelled.
 */
#[Fillable(['title', 'description', 'created_by_user_id', 'opens_at', 'closes_at', 'closed_at', 'cancelled_at'])]
class Vote extends Model
{
    use HasFactory, LogsActivity;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<VoteOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(VoteOption::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<AccountPosition, $this>
     */
    public function accountPositions(): BelongsToMany
    {
        return $this->belongsToMany(AccountPosition::class, 'vote_account_positions', 'vote_id', 'account_position_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function voters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vote_voters', 'vote_id', 'user_id');
    }

    public function status(?\DateTimeInterface $now = null): string
    {
        $now ??= now();

        if ($this->cancelled_at !== null) {
            return self::STATUS_CANCELLED;
        }

        if ($this->closed_at !== null || $this->closes_at->lte($now)) {
            return self::STATUS_CLOSED;
        }

        if ($this->opens_at !== null && $this->opens_at->gt($now)) {
            return self::STATUS_SCHEDULED;
        }

        return self::STATUS_OPEN;
    }

    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return ['created_at', 'updated_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
