<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\ChamberPosition;
use App\Models\Invitation;
use App\Models\Member;
use App\Models\MemberStatusLog;
use App\Models\PositionAppointment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Aggregates the figures shown on the dashboard. Applicant-level figures are returned only to roles
 * that may view member records.
 */
class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, int $months): array
    {
        $summary = [
            'generated_at' => now()->toIso8601String(),
            'can_view_members' => Gate::forUser($user)->allows('view-members'),
            'positions_current' => PositionAppointment::query()->current()->count(),
            'positions_total' => ChamberPosition::query()->where('is_active', true)->count(),
            'active_invitations' => Gate::forUser($user)->allows('manage-invitations')
                ? Invitation::query()->usable()->count()
                : null,
        ];

        if (! $summary['can_view_members']) {
            return $summary;
        }

        return $summary + [
            'kpis' => $this->kpis(),
            'monthly' => $this->monthly($months),
            'categories' => $this->categories(),
            'recent_applications' => $this->recentApplications(),
            'activity' => $this->activity(),
            'expiring_terms' => $this->expiringTerms(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function kpis(): array
    {
        $counts = Member::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'pending' => (int) ($counts[MemberStatus::Pending->value] ?? 0),
            'approved' => (int) ($counts[MemberStatus::Approved->value] ?? 0),
            'rejected' => (int) ($counts[MemberStatus::Rejected->value] ?? 0),
            'applied_this_month' => Member::query()->where('applied_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Applications and approvals per month, oldest first.
     *
     * @return array{labels: array<int, string>, applications: array<int, int>, approvals: array<int, int>}
     */
    private function monthly(int $months): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $applications = Member::query()
            ->where('applied_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(applied_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $approvals = Member::query()
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(approved_at, '%Y-%m') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $labels = [];
        $appliedSeries = [];
        $approvedSeries = [];

        for ($index = 0; $index < $months; $index++) {
            $month = $start->copy()->addMonths($index);
            $key = $month->format('Y-m');

            $labels[] = $month->locale(app()->getLocale())->isoFormat('MMM YY');
            $appliedSeries[] = (int) ($applications[$key] ?? 0);
            $approvedSeries[] = (int) ($approvals[$key] ?? 0);
        }

        return ['labels' => $labels, 'applications' => $appliedSeries, 'approvals' => $approvedSeries];
    }

    /**
     * @return array<int, array{name: string, total: int}>
     */
    private function categories(): array
    {
        return DB::table('members')
            ->join('business_categories', 'business_categories.id', '=', 'members.business_category_id')
            ->whereNull('members.deleted_at')
            ->groupBy('business_categories.id', 'business_categories.name_bm')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(6)
            ->get([
                'business_categories.name_bm as name',
                DB::raw('COUNT(*) as total'),
            ])
            ->map(fn ($row): array => ['name' => (string) $row->name, 'total' => (int) $row->total])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentApplications(): array
    {
        return Member::query()
            ->latest('applied_at')
            ->limit(5)
            ->get(['id', 'applicant_name', 'business_name', 'status', 'applied_at'])
            ->map(fn (Member $member): array => [
                'id' => $member->id,
                'applicant_name' => $member->applicant_name,
                'business_name' => $member->business_name,
                'status' => $member->status->value,
                'status_label' => $member->status->label(),
                'applied_at' => $member->applied_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activity(): array
    {
        return MemberStatusLog::query()
            ->with(['member:id,applicant_name', 'changedBy:id,name'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (MemberStatusLog $log): array => [
                'member_id' => $log->member_id,
                'applicant_name' => $log->member?->applicant_name,
                'to_status' => $log->to_status->value,
                'to_status_label' => $log->to_status->label(),
                'changed_by' => $log->changedBy?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * Current terms that end within the next 60 days.
     *
     * @return array<int, array<string, mixed>>
     */
    private function expiringTerms(): array
    {
        $today = Carbon::today();

        return PositionAppointment::query()
            ->current()
            ->whereBetween('term_end', [$today, $today->copy()->addDays(60)])
            ->with(['member:id,applicant_name,business_name', 'chamberPosition:id,name_bm'])
            ->orderBy('term_end')
            ->limit(8)
            ->get()
            ->map(fn (PositionAppointment $appointment): array => [
                'member_id' => $appointment->member_id,
                'applicant_name' => $appointment->member?->applicant_name,
                'position' => $appointment->chamberPosition?->name_bm,
                'term_end' => $appointment->term_end?->toDateString(),
                'days_left' => $today->diffInDays($appointment->term_end, false),
            ])
            ->values()
            ->all();
    }
}
