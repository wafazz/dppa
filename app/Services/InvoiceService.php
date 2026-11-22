<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Models\FeeType;
use App\Models\Member;
use App\Models\MembershipInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues the charges fee types define, one invoice per member, fee and period, and handles waivers.
 *
 * A period key is "lifetime" for a one-off charge, "2027" for a membership year, and "2027-03" for a month.
 */
class InvoiceService
{
    public const LIFETIME_KEY = 'lifetime';

    public function __construct(
        private PaymentSettings $settings,
        private EmailNotices $notices,
        private ActivityLogger $activity,
    ) {}

    /**
     * Issue the fee to every approved member it applies to, for each period the request covers.
     * Monthly fees given a year (2027) cover all twelve months. Running it again only fills the gaps.
     *
     * @return array{created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function generate(FeeType $fee, ?string $period, User $actor): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($this->periodKeysFor($fee, $period) as $key) {
            Member::query()
                ->where('status', MemberStatus::Approved)
                ->when($fee->business_category_id !== null, fn ($query) => $query->where('business_category_id', $fee->business_category_id))
                ->orderBy('id')
                ->chunkById(200, function ($members) use ($fee, $key, $actor, &$created, &$skipped): void {
                    foreach ($members as $member) {
                        $this->issue($member, $fee, $key, $actor) === null ? $skipped++ : $created++;
                    }
                });
        }

        $this->activity->record('invoice.generated', $fee->name_bm, $actor, $fee, [
            'created' => $created,
            'skipped' => $skipped,
            'period' => $period,
        ]);

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * When the setting is on, a newly approved member gets the current period of every active fee that applies to them.
     */
    public function issueOnApproval(Member $member): void
    {
        if (! $this->settings->all()['auto_invoice_on_approval']) {
            return;
        }

        FeeType::query()
            ->active()
            ->where(fn ($query) => $query
                ->whereNull('business_category_id')
                ->orWhere('business_category_id', $member->business_category_id))
            ->get()
            ->each(fn (FeeType $fee) => $this->issue($member, $fee, $this->currentKeyFor($fee), null));
    }

    /**
     * Creates one invoice. Returns null when the member already has this fee for this period.
     */
    public function issue(Member $member, FeeType $fee, string $periodKey, ?User $actor): ?MembershipInvoice
    {
        $invoice = MembershipInvoice::query()->firstOrCreate(
            ['member_id' => $member->id, 'fee_type_id' => $fee->id, 'period_key' => $periodKey],
            [
                'amount_cents' => $fee->amount_cents,
                'paid_cents' => 0,
                'status' => MembershipInvoice::STATUS_UNPAID,
                'due_on' => Carbon::today()->addDays($fee->due_days),
                'issued_at' => now(),
                'issued_by_user_id' => $actor?->id,
            ],
        );

        if (! $invoice->wasRecentlyCreated) {
            return null;
        }

        $invoice->update(['number' => $this->numberFor($invoice)]);

        if ($this->settings->all()['send_invoice_emails'] && $member->email !== null) {
            $this->notices->deliver('invoice_issued', (string) $member->email, [
                'name' => $member->applicant_name,
                'fee' => $fee->name_bm,
                'amount' => Money::format($invoice->amount_cents, $this->symbol()),
                'due' => $invoice->due_on->translatedFormat('j M Y'),
                'number' => (string) $invoice->number,
            ], route('member.invoices.index'));
        }

        return $invoice;
    }

    /**
     * The period keys a generate request covers. Mismatched requests are refused with a clear message.
     *
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public function periodKeysFor(FeeType $fee, ?string $period): array
    {
        $period = $period === null || $period === '' ? null : $period;

        return match ($fee->frequency) {
            FeeType::FREQUENCY_LIFETIME => [self::LIFETIME_KEY],
            FeeType::FREQUENCY_YEARLY => $this->yearlyKeys($period),
            FeeType::FREQUENCY_MONTHLY => $this->monthlyKeys($period),
            default => throw ValidationException::withMessages(['period' => __('app.billing.error_period')]),
        };
    }

    /**
     * The period a fee is charged for today, used when a member is approved.
     */
    public function currentKeyFor(FeeType $fee): string
    {
        return match ($fee->frequency) {
            FeeType::FREQUENCY_MONTHLY => now()->format('Y-m'),
            FeeType::FREQUENCY_YEARLY => now()->format('Y'),
            default => self::LIFETIME_KEY,
        };
    }

    /**
     * Cancels what is still owed on an invoice. Money already paid stays on record.
     *
     * @throws ValidationException
     */
    public function waive(MembershipInvoice $invoice, User $actor, string $reason): MembershipInvoice
    {
        return DB::transaction(function () use ($invoice, $actor, $reason): MembershipInvoice {
            $locked = MembershipInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [MembershipInvoice::STATUS_PAID, MembershipInvoice::STATUS_WAIVED], true)) {
                throw ValidationException::withMessages(['invoice' => __('app.billing.error_closed')]);
            }

            $locked->update([
                'status' => MembershipInvoice::STATUS_WAIVED,
                'waived_at' => now(),
                'waived_reason' => $reason,
            ]);

            $this->activity->record('invoice.waived', $reason, $actor, $locked, ['number' => $locked->number]);

            return $locked->refresh();
        });
    }

    /**
     * Sets the status from the amounts: paid when fully covered, partial when some is paid, otherwise unpaid.
     */
    public function refreshStatus(MembershipInvoice $invoice): void
    {
        if ($invoice->status === MembershipInvoice::STATUS_WAIVED) {
            return;
        }

        $status = match (true) {
            $invoice->paid_cents >= $invoice->amount_cents => MembershipInvoice::STATUS_PAID,
            $invoice->paid_cents > 0 => MembershipInvoice::STATUS_PARTIAL,
            default => MembershipInvoice::STATUS_UNPAID,
        };

        $invoice->forceFill(['status' => $status])->save();
    }

    public function symbol(): string
    {
        return (string) $this->settings->all()['currency_symbol'];
    }

    /**
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    private function yearlyKeys(?string $period): array
    {
        if ($period === null || strlen($period) !== 4) {
            throw ValidationException::withMessages(['period' => __('app.billing.error_period_year')]);
        }

        return [$period];
    }

    /**
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    private function monthlyKeys(?string $period): array
    {
        if ($period === null) {
            throw ValidationException::withMessages(['period' => __('app.billing.error_period_year')]);
        }

        if (strlen($period) === 4) {
            return collect(range(1, 12))->map(fn (int $month): string => sprintf('%s-%02d', $period, $month))->all();
        }

        return [$period];
    }

    private function numberFor(MembershipInvoice $invoice): string
    {
        return sprintf(
            '%s-%d-%06d',
            (string) $this->settings->all()['invoice_prefix'],
            $invoice->issued_at->year,
            $invoice->id,
        );
    }
}
