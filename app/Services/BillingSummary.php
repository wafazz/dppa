<?php

namespace App\Services;

use App\Models\FeeType;
use App\Models\Member;
use App\Models\MembershipInvoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * The figures the committee and the member see: what is owed, what came in, and what is overdue.
 */
class BillingSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forChamber(): array
    {
        $today = today();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();

        $outstanding = (int) MembershipInvoice::query()->open()
            ->selectRaw('COALESCE(SUM(amount_cents - paid_cents), 0) as total')
            ->value('total');

        $byFee = MembershipInvoice::query()->open()
            ->select('fee_type_id', DB::raw('COUNT(*) as invoices'), DB::raw('COALESCE(SUM(amount_cents - paid_cents), 0) as owed'))
            ->groupBy('fee_type_id')
            ->get()
            ->map(fn ($row): array => [
                'fee_type_id' => (int) $row->fee_type_id,
                'name' => (string) FeeType::query()->whereKey($row->fee_type_id)->value('name_bm'),
                'invoices' => (int) $row->invoices,
                'owed_cents' => (int) $row->owed,
            ])
            ->values()
            ->all();

        return [
            'outstanding_cents' => $outstanding,
            'open_invoices' => MembershipInvoice::query()->open()->count(),
            'overdue_invoices' => MembershipInvoice::query()->open()->where('due_on', '<', $today)->count(),
            'collected_month_cents' => (int) Payment::query()->counted()
                ->whereBetween('paid_on', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('amount_cents'),
            'collected_total_cents' => (int) Payment::query()->counted()->sum('amount_cents'),
            'members_without_invoices' => Member::query()->where('status', 'approved')
                ->whereDoesntHave('invoices')
                ->count(),
            'by_fee' => $byFee,
        ];
    }

    /**
     * What one member owes and has paid, for their own page.
     *
     * @return array<string, mixed>
     */
    public function forMember(Member $member): array
    {
        $invoices = MembershipInvoice::query()
            ->with(['feeType', 'payments'])
            ->where('member_id', $member->id)
            ->orderByDesc('issued_at')
            ->get();

        return [
            'owed_cents' => (int) $invoices->sum(fn (MembershipInvoice $invoice): int => $invoice->balanceCents()),
            'invoices' => $invoices->map(fn (MembershipInvoice $invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'fee' => (string) $invoice->feeType->name_bm,
                'period' => $invoice->period_key === 'lifetime' ? null : $invoice->period_key,
                'amount_cents' => $invoice->amount_cents,
                'paid_cents' => $invoice->paid_cents,
                'balance_cents' => $invoice->balanceCents(),
                'status' => $invoice->status,
                'overdue' => $invoice->isOverdue(),
                'due_on' => $invoice->due_on->translatedFormat('j M Y'),
                'payments' => $invoice->payments->reject(fn (Payment $payment): bool => $payment->isVoided())
                    ->map(fn (Payment $payment): array => [
                        'receipt' => $payment->receipt_number,
                        'amount_cents' => $payment->amount_cents,
                        'paid_on' => $payment->paid_on->translatedFormat('j M Y'),
                        'method' => $payment->method,
                    ])->values()->all(),
            ])->all(),
        ];
    }
}
