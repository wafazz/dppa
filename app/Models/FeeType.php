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
#[Fillable(['code', 'name_bm', 'name_en', 'description', 'amount_cents', 'frequency', 'business_category_id', 'due_days', 'is_active', 'sort_order'])]
class FeeType extends Model
{
    public const FREQUENCY_YEARLY = 'yearly';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_LIFETIME = 'lifetime';

    /** @var array<int, string> */
    public const FREQUENCIES = [self::FREQUENCY_YEARLY, self::FREQUENCY_MONTHLY, self::FREQUENCY_LIFETIME];

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
