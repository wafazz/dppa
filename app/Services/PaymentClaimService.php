<?php

namespace App\Services;

use App\Models\MembershipInvoice;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A member reports a payment they made; the committee confirms it into a real payment or rejects it.
 */
class PaymentClaimService
{
    public function __construct(
        private PaymentService $payments,
        private PaymentSettings $settings,
        private ActivityLogger $activity,
    ) {}

    /**
     * @param  array{amount_cents: int, method: string, reference?: ?string, paid_on: string, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function submit(MembershipInvoice $invoice, array $data): PaymentClaim
    {
        $invoice->refresh();

        if (in_array($invoice->status, [MembershipInvoice::STATUS_PAID, MembershipInvoice::STATUS_WAIVED], true)) {
            throw ValidationException::withMessages(['invoice' => __('app.billing.error_closed')]);
        }

        if ($data['amount_cents'] > $invoice->balanceCents()) {
            throw ValidationException::withMessages(['amount' => __('app.billing.error_over_balance')]);
        }

        if (! in_array($data['method'], $this->settings->enabledMethods(), true)) {
            throw ValidationException::withMessages(['method' => __('app.billing.error_method_off')]);
        }

        $claim = PaymentClaim::query()->create([
            'membership_invoice_id' => $invoice->id,
            'member_id' => $invoice->member_id,
            'amount_cents' => $data['amount_cents'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'paid_on' => $data['paid_on'],
            'note' => $data['note'] ?? null,
            'status' => PaymentClaim::STATUS_PENDING,
        ]);

        $this->activity->record('payment_claim.submitted', $invoice->number, null, $claim, ['amount_cents' => $data['amount_cents']]);

        return $claim;
    }

    /**
     * Turns the report into a payment on its invoice. The invoice is locked, so the balance is checked again.
     *
     * @throws ValidationException
     */
    public function confirm(PaymentClaim $claim, User $actor): Payment
    {
        return DB::transaction(function () use ($claim, $actor): Payment {
            $locked = PaymentClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentClaim::STATUS_PENDING) {
                throw ValidationException::withMessages(['claim' => __('app.billing.error_claim_reviewed')]);
            }

            $invoice = MembershipInvoice::query()->findOrFail($locked->membership_invoice_id);

            $payment = $this->payments->record($invoice, [
                'amount_cents' => $locked->amount_cents,
                'method' => $locked->method,
                'reference' => $locked->reference,
                'paid_on' => $locked->paid_on->toDateString(),
                'note' => trim(__('app.billing.claim_note').' '.(string) $locked->note),
            ], $actor);

            $locked->update([
                'status' => PaymentClaim::STATUS_CONFIRMED,
                'payment_id' => $payment->id,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->activity->record('payment_claim.confirmed', $invoice->number, $actor, $locked, ['payment' => $payment->receipt_number]);

            return $payment;
        });
    }

    /**
     * @throws ValidationException
     */
    public function reject(PaymentClaim $claim, User $actor, string $reason): PaymentClaim
    {
        return DB::transaction(function () use ($claim, $actor, $reason): PaymentClaim {
            $locked = PaymentClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentClaim::STATUS_PENDING) {
                throw ValidationException::withMessages(['claim' => __('app.billing.error_claim_reviewed')]);
            }

            $locked->update([
                'status' => PaymentClaim::STATUS_REJECTED,
                'reject_reason' => $reason,
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $this->activity->record('payment_claim.rejected', $reason, $actor, $locked);

            return $locked->refresh();
        });
    }
}
