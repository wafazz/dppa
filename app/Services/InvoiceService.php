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
 * Issues the charges fees define, one invoice per member, fee and period, and handles waivers.
 *
 * A period key is "lifetime" for a one-off charge, "2027" for a membership year, and "2027-03" for a month.
 * Each invoice takes the price of its period when it is issued.
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
     * Issue one period of a fee to every approved member it applies to. A monthly period given a year covers all
     * twelve months. Running it again only fills the gaps.
     *
     * @return array{created: int, skipped: int}
     *
     * @throws ValidationException
     */
    public function generate(FeeType $fee, string $periodType, ?string $period, User $actor): array
    {
        $amount = $this->priceOrFail($fee, $periodType);
        $created = 0;
        $skipped = 0;

        foreach ($this->periodKeysFor($periodType, $period) as $key) {
            Member::query()
                ->where('status', MemberStatus::Approved)
                ->when($fee->business_category_id !== null, fn ($query) => $query->where('business_category_id', $fee->business_category_id))
                ->orderBy('id')
                ->chunkById(200, function ($members) use ($fee, $periodType, $key, $amount, $actor, &$created, &$skipped): void {
                    foreach ($members as $member) {
                        $this->issue($member, $fee, $periodType, $key, $amount, $actor, notify: true) === null ? $skipped++ : $created++;
                    }
                });
        }

        $this->activity->record('invoice.generated', $fee->name_bm, $actor, $fee, [
            'created' => $created,
            'skipped' => $skipped,
            'period_type' => $periodType,
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
            ->each(function (FeeType $fee) use ($member): void {
                [$periodType, $key] = $this->defaultPeriodFor($fee);

                $this->issue($member, $fee, $periodType, $key, (int) $fee->priceFor($periodType), null, notify: true);
            });
    }

    /**
     * The periods a member may choose when paying a fee: the months still to come this year, the current and next
     * membership year, or the lifetime charge.
     *
     * @return array<int, array{period_type: string, period_key: string, label: string}>
     */
    public function memberPeriodOptions(FeeType $fee): array
    {
        $options = [];
        $today = Carbon::today();

        if ($fee->priceFor(FeeType::PERIOD_MONTHLY) !== null) {
            for ($month = $today->month; $month <= 12; $month++) {
                $start = Carbon::create($today->year, $month, 1);

                $options[] = [
                    'period_type' => FeeType::PERIOD_MONTHLY,
                    'period_key' => $start->format('Y-m'),
                    'label' => $start->translatedFormat('F Y'),
                ];
            }
        }

        if ($fee->priceFor(FeeType::PERIOD_YEARLY) !== null) {
            foreach ([$today->year, $today->year + 1] as $year) {
                $options[] = [
                    'period_type' => FeeType::PERIOD_YEARLY,
                    'period_key' => (string) $year,
                    'label' => __('app.billing.period_year', ['year' => $year]),
                ];
            }
        }

        if ($fee->priceFor(FeeType::PERIOD_LIFETIME) !== null) {
            $options[] = [
                'period_type' => FeeType::PERIOD_LIFETIME,
                'period_key' => self::LIFETIME_KEY,
                'label' => __('app.billing.period_lifetime'),
            ];
        }

        return $options;
    }

    /**
     * A member chooses a period to pay. Returns the invoice for it, creating it if needed.
     *
     * @throws ValidationException
     */
    public function chooseForMember(Member $member, FeeType $fee, string $periodType, string $periodKey): MembershipInvoice
    {
        $allowed = collect($this->memberPeriodOptions($fee))
            ->contains(fn (array $option): bool => $option['period_type'] === $periodType && $option['period_key'] === $periodKey);

        if (! $allowed || ! $this->appliesTo($fee, $member)) {
            throw ValidationException::withMessages(['period' => __('app.billing.error_period_not_offered')]);
        }

        $amount = $this->priceOrFail($fee, $periodType);

        return $this->issue($member, $fee, $periodType, $periodKey, $amount, null, notify: false)
            ?? MembershipInvoice::query()
                ->where('member_id', $member->id)
                ->where('fee_type_id', $fee->id)
                ->where('period_key', $periodKey)
                ->firstOrFail();
    }

    public function appliesTo(FeeType $fee, Member $member): bool
    {
        return $fee->business_category_id === null || $fee->business_category_id === $member->business_category_id;
    }

    /**
     * Creates one invoice. Returns null when the member already has this fee for this period.
     */
    public function issue(Member $member, FeeType $fee, string $periodType, string $periodKey, int $amountCents, ?User $actor, bool $notify): ?MembershipInvoice
    {
        $invoice = MembershipInvoice::query()->firstOrCreate(
            ['member_id' => $member->id, 'fee_type_id' => $fee->id, 'period_key' => $periodKey],
            [
                'amount_cents' => $amountCents,
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

        if ($notify && $this->settings->all()['send_invoice_emails'] && $member->email !== null) {
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
     * The period keys a generate request covers. A period that does not fit the period type is refused.
     *
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public function periodKeysFor(string $periodType, ?string $period): array
    {
        $period = $period === null || $period === '' ? null : $period;

        return match ($periodType) {
            FeeType::PERIOD_LIFETIME => [self::LIFETIME_KEY],
            FeeType::PERIOD_YEARLY => $this->yearlyKeys($period),
            FeeType::PERIOD_MONTHLY => $this->monthlyKeys($period),
            default => throw ValidationException::withMessages(['period_type' => __('app.billing.error_period')]),
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
     * The period a fee is charged for when a member is approved: the yearly price if offered, otherwise lifetime,
     * otherwise the current month.
     *
     * @return array{0: string, 1: string}
     */
    private function defaultPeriodFor(FeeType $fee): array
    {
        if ($fee->priceFor(FeeType::PERIOD_YEARLY) !== null) {
            return [FeeType::PERIOD_YEARLY, now()->format('Y')];
        }

        if ($fee->priceFor(FeeType::PERIOD_LIFETIME) !== null) {
            return [FeeType::PERIOD_LIFETIME, self::LIFETIME_KEY];
        }

        return [FeeType::PERIOD_MONTHLY, now()->format('Y-m')];
    }

    /**
     * @throws ValidationException
     */
    private function priceOrFail(FeeType $fee, string $periodType): int
    {
        $price = $fee->priceFor($periodType);

        if ($price === null) {
            throw ValidationException::withMessages(['period_type' => __('app.billing.error_not_offered')]);
        }

        return $price;
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
