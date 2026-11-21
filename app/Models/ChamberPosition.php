<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ChamberPositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name_bm', 'name_en', 'is_active', 'sort_order'])]
class ChamberPosition extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /** @use HasFactory<ChamberPositionFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get every appointment made to this position.
     *
     * @return HasMany<PositionAppointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(PositionAppointment::class);
    }

    /**
     * Limit the query to positions that may be assigned.
     *
     * @param  Builder<ChamberPosition>  $query
     * @return Builder<ChamberPosition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
