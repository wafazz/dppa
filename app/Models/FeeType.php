<?php

namespace App\Models;

use Database\Factories\FeeTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of charge the chamber collects, such as an annual subscription or an entrance fee.
 */
#[Fillable(['code', 'name_bm', 'name_en', 'description', 'monthly_cents', 'yearly_cents', 'lifetime_cents', 'business_category_id', 'due_days', 'is_active', 'sort_order'])]
class FeeType extends Model
{
    public const PERIOD_MONTHLY = 'monthly';

    public const PERIOD_YEARLY = 'yearly';

    public const PERIOD_LIFETIME = 'lifetime';

    /** @var array<int, string> */
    public const PERIODS = [self::PERIOD_MONTHLY, self::PERIOD_YEARLY, self::PERIOD_LIFETIME];

    /**
     * The price in sen for a period, or null when the fee does not offer that period.
     */
    public function priceFor(string $period): ?int
    {
        return match ($period) {
            self::PERIOD_MONTHLY => $this->monthly_cents,
            self::PERIOD_YEARLY => $this->yearly_cents,
            self::PERIOD_LIFETIME => $this->lifetime_cents,
            default => null,
        };
    }

    /**
     * The periods this fee can be paid for, in the order monthly, yearly, lifetime.
     *
     * @return array<int, string>
     */
    public function offeredPeriods(): array
    {
        return array_values(array_filter(self::PERIODS, fn (string $period): bool => $this->priceFor($period) !== null));
    }

    /** @use HasFactory<FeeTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<BusinessCategory, $this>
     */
    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class);
    }

    /**
     * @return HasMany<MembershipInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(MembershipInvoice::class);
    }

    /**
     * @param  Builder<FeeType>  $query
     * @return Builder<FeeType>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
