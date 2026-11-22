<?php

namespace App\Services;

use App\Models\MembershipInvoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records money received against invoices and voids mistakes. Every change happens under a row lock on the invoice,
 * so two people recording payments at once cannot pay more than is owed.
 */
class PaymentService
{
    public function __construct(
        private InvoiceService $invoices,
        private PaymentSettings $settings,
        private EmailNotices $notices,
        private ActivityLogger $activity,
    ) {}

    /**
     * @param  array{amount_cents: int, method: string, reference?: ?string, paid_on: string, note?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function record(MembershipInvoice $invoice, array $data, User $actor): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $data, $actor): Payment {
            $locked = MembershipInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if (! in_array($data['method'], $this->settings->enabledMethods(), true)) {
                throw ValidationException::withMessages(['method' => __('app.billing.error_method_off')]);
            }

            if (in_array($locked->status, [MembershipInvoice::STATUS_PAID, MembershipInvoice::STATUS_WAIVED], true)) {
                throw ValidationException::withMessages(['amount_cents' => __('app.billing.error_closed')]);
            }

            if ($data['amount_cents'] > $locked->balanceCents()) {
                throw ValidationException::withMessages(['amount' => __('app.billing.error_over_balance')]);
            }

            $payment = Payment::query()->create([
                'membership_invoice_id' => $locked->id,
                'member_id' => $locked->member_id,
                'amount_cents' => $data['amount_cents'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'paid_on' => $data['paid_on'],
                'note' => $data['note'] ?? null,
                'recorded_by_user_id' => $actor->id,
            ]);

            $payment->update(['receipt_number' => $this->receiptNumberFor($payment)]);

            $locked->paid_cents += $data['amount_cents'];
            $this->invoices->refreshStatus($locked);

            $this->activity->record('payment.recorded', $payment->receipt_number, $actor, $payment, [
                'invoice' => $locked->number,
                'amount_cents' => $data['amount_cents'],
                'method' => $data['method'],
            ]);

            return $payment;
        });

        $this->notifyReceived($payment);

        return $payment;
    }

    /**
     * Voids a payment that was entered by mistake. The invoice balance goes back up.
     *
     * @throws ValidationException
     */
    public function void(Payment $payment, User $actor, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw ValidationException::withMessages(['payment' => __('app.billing.error_already_voided')]);
            }

            $invoice = MembershipInvoice::query()->whereKey($locked->membership_invoice_id)->lockForUpdate()->firstOrFail();

            $locked->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by_user_id' => $actor->id,
            ]);

            $invoice->paid_cents = max(0, $invoice->paid_cents - $locked->amount_cents);
            $this->invoices->refreshStatus($invoice);

            $this->activity->record('payment.voided', $reason, $actor, $locked, ['invoice' => $invoice->number]);

            return $locked->refresh();
        });
    }

    private function notifyReceived(Payment $payment): void
    {
        $payment->loadMissing('invoice.feeType', 'member');
        $invoice = $payment->invoice;
        $member = $payment->member;

        if ($member->email === null) {
            return;
        }

        $this->notices->deliver('payment_received', (string) $member->email, [
            'name' => $member->applicant_name,
            'fee' => (string) $invoice->feeType->name_bm,
            'amount' => Money::format($payment->amount_cents, $this->symbol()),
            'receipt' => (string) $payment->receipt_number,
            'balance' => Money::format($invoice->balanceCents(), $this->symbol()),
        ], route('member.invoices.index'));
    }

    private function receiptNumberFor(Payment $payment): string
    {
        return sprintf(
            '%s-%d-%06d',
            (string) $this->settings->all()['receipt_prefix'],
            $payment->paid_on->year,
            $payment->id,
        );
    }

    private function symbol(): string
    {
        return (string) $this->settings->all()['currency_symbol'];
    }
}
