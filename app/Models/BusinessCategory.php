<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\BusinessCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_bm', 'name_en', 'is_active', 'sort_order'])]
class BusinessCategory extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /** @use HasFactory<BusinessCategoryFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the members in this business category.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'business_category_id');
    }

    /**
     * Limit the query to categories that may be selected.
     *
     * @param  Builder<BusinessCategory>  $query
     * @return Builder<BusinessCategory>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name_bm');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
