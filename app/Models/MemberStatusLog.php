<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\MemberStatusLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['member_id', 'from_status', 'to_status', 'changed_by_user_id', 'note'])]
class MemberStatusLog extends Model
{
    /** @use HasFactory<MemberStatusLogFactory> */
    use HasFactory;

    /**
     * Status logs are append-only and only record creation time.
     */
    public const UPDATED_AT = null;

    /**
     * Get the member whose status changed.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the committee user who changed the status.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => MemberStatus::class,
            'to_status' => MemberStatus::class,
            'created_at' => 'datetime',
        ];
    }
}
