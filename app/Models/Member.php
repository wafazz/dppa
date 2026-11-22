<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Models\Concerns\LogsActivity;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'member_no',
    'applicant_name',
    'business_name',
    'business_category_id',
    'business_address',
    'phone',
    'email',
    'ssm_no',
    'business_description',
    'business_needs',
    'search_text',
    'status',
    'applied_at',
    'approved_at',
    'rejected_at',
    'consent_given_at',
    'consent_version',
    'announcement_consent_at',
    'invitation_id',
])]
class Member extends Model
{
    /**
     * @return array<int, string>
     */
    protected function activityExcluded(): array
    {
        return array_merge(['created_at', 'updated_at'], []);
    }

    /** @use HasFactory<MemberFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * The member's login account, once one has been created on approval.
     *
     * @return HasOne<User, $this>
     */
    public function account(): HasOne
    {
        return $this->hasOne(User::class, 'member_id');
    }

    /**
     * Get the invitation the member registered through, if any.
     *
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Get the business category of the member.
     *
     * @return BelongsTo<BusinessCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    /**
     * Membership fees issued to the member.
     *
     * @return HasMany<MembershipInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(MembershipInvoice::class);
    }

    /**
     * Get the status history of the member, newest first.
     *
     * @return HasMany<MemberStatusLog, $this>
     */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(MemberStatusLog::class)->orderByDesc('id');
    }

    /**
     * Get every chamber position appointment held by the member.
     *
     * @return HasMany<PositionAppointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(PositionAppointment::class)->orderByDesc('term_start');
    }

    /**
     * Apply the committee search and filter options.
     *
     * @param  Builder<Member>  $query
     * @param  array{q?: ?string, status?: ?string, category_id?: ?int, services?: ?string}  $filters
     * @return Builder<Member>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = '%'.self::escapeLike($term).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('applicant_name', 'like', $like)
                        ->orWhere('business_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('ssm_no', 'like', $like)
                        ->orWhere('member_no', 'like', $like);
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int|string $categoryId): Builder => $query->where('business_category_id', $categoryId))
            ->when($filters['services'] ?? null, function (Builder $query, string $text): void {
                $query->where('search_text', 'like', '%'.self::escapeLike($text).'%');
            });
    }

    /**
     * Escape SQL LIKE wildcards in user input.
     */
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'consent_given_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }
}
