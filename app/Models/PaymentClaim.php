<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment a member reports ("I paid by bank transfer on ..."). It becomes a real payment only when the
 * committee confirms it.
 */
#[Fillable(['membership_invoice_id', 'member_id', 'amount_cents', 'method', 'reference', 'paid_on', 'note', 'status', 'payment_id', 'reject_reason', 'reviewed_by_user_id', 'reviewed_at'])]
class PaymentClaim extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MembershipInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(MembershipInvoice::class, 'membership_invoice_id');
    }

    /**
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * @param  Builder<PaymentClaim>  $query
     * @return Builder<PaymentClaim>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
