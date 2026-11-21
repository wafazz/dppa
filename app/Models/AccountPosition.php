<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\AccountPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Management position of a staff account. A lower level number means a higher rank.
 */
#[Fillable(['name_bm', 'name_en', 'level', 'is_active'])]
class AccountPosition extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /** @use HasFactory<AccountPositionFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'account_position_id');
    }

    /**
     * @param  Builder<AccountPosition>  $query
     * @return Builder<AccountPosition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('level');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
