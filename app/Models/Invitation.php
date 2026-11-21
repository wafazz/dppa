<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single-purpose registration link. Members who register through it are linked back to it.
 */
#[Fillable(['token', 'created_by_user_id', 'note', 'recipient_email', 'expires_at', 'max_uses', 'used_count', 'revoked_at'])]
class Invitation extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], ['token']);
    }

    /** @use HasFactory<InvitationFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Whether the link can still be used for a registration.
     */
    public function isUsable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->used_count < $this->max_uses
            && $this->createdBy?->is_active === true;
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereColumn('used_count', '<', 'max_uses')
            ->whereHas('createdBy', fn (Builder $creator) => $creator->where('is_active', true));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'used_count' => 'integer',
        ];
    }
}
