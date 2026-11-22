<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against an invoice. Payments are voided, never deleted.
 */
#[Fillable(['receipt_number', 'membership_invoice_id', 'member_id', 'amount_cents', 'method', 'reference', 'paid_on', 'note', 'recorded_by_user_id', 'voided_at', 'void_reason', 'voided_by_user_id'])]
class Payment extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'voided_at' => 'datetime',
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
     * @param  Builder<Payment>  $query
     * @return Builder<Payment>
     */
    public function scopeCounted(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
