<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\PositionAppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'member_id',
    'chamber_position_id',
    'term_start',
    'term_end',
    'is_current',
    'notes',
    'created_by_user_id',
])]
class PositionAppointment extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /** @use HasFactory<PositionAppointmentFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the member who holds or held the position.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the chamber position that was assigned.
     *
     * @return BelongsTo<ChamberPosition, $this>
     */
    public function chamberPosition(): BelongsTo
    {
        return $this->belongsTo(ChamberPosition::class);
    }

    /**
     * Get the committee user who recorded the appointment.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Limit the query to appointments marked as current.
     *
     * @param  Builder<PositionAppointment>  $query
     * @return Builder<PositionAppointment>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term_start' => 'date',
            'term_end' => 'date',
            'is_current' => 'boolean',
        ];
    }
}
