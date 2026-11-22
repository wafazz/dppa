<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A charge issued to one member for one fee. Its status moves from unpaid to partial to paid, or to waived.
 * Overdue is not stored: it is an unpaid or partly paid invoice whose due date has passed.
 */
#[Fillable(['number', 'member_id', 'fee_type_id', 'period_key', 'amount_cents', 'paid_cents', 'status', 'due_on', 'issued_at', 'waived_at', 'waived_reason', 'reminded_at', 'issued_by_user_id'])]
class MembershipInvoice extends Model
{
    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_WAIVED = 'waived';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'issued_at' => 'datetime',
            'waived_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @return BelongsTo<FeeType, $this>
     */
    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_on')->orderByDesc('id');
    }

    /**
     * What is still owed, in sen. Waived and paid invoices owe nothing.
     */
    public function balanceCents(): int
    {
        if (in_array($this->status, [self::STATUS_PAID, self::STATUS_WAIVED], true)) {
            return 0;
        }

        return max(0, $this->amount_cents - $this->paid_cents);
    }

    public function isOverdue(?Carbon $today = null): bool
    {
        $today ??= Carbon::today();

        return $this->balanceCents() > 0 && $this->due_on->lt($today);
    }

    /**
     * @param  Builder<MembershipInvoice>  $query
     * @return Builder<MembershipInvoice>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_UNPAID, self::STATUS_PARTIAL]);
    }
}
