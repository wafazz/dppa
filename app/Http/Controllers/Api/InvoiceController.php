<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateInvoicesRequest;
use App\Http\Requests\RecordPaymentRequest;
use App\Models\FeeType;
use App\Models\MembershipInvoice;
use App\Models\Payment;
use App\Services\BillingSummary;
use App\Services\InvoiceService;
use App\Services\Money;
use App\Services\PaymentService;
use App\Services\PaymentSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request, PaymentSettings $settings): JsonResponse
    {
        $query = MembershipInvoice::query()
            ->with(['member', 'feeType', 'payments'])
            ->when($request->string('status')->toString() !== '', fn (Builder $q) => $this->filterStatus($q, $request->string('status')->toString()))
            ->when($request->integer('fee_type_id') > 0, fn (Builder $q) => $q->where('fee_type_id', $request->integer('fee_type_id')))
            ->when($request->integer('year') > 0, fn (Builder $q) => $q->where('period_key', 'like', $request->integer('year').'%'))
            ->when($request->string('q')->trim()->toString() !== '', function (Builder $q) use ($request): void {
                $term = '%'.$request->string('q')->trim()->toString().'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('number', 'like', $term)
                    ->orWhereHas('member', fn (Builder $member) => $member
                        ->where('applicant_name', 'like', $term)
                        ->orWhere('member_no', 'like', $term)));
            })
            ->orderByDesc('issued_at')
            ->orderByDesc('id');

        $page = $query->paginate(self::PER_PAGE);
        $symbol = (string) $settings->all()['currency_symbol'];

        return response()->json([
            'data' => collect($page->items())->map(fn (MembershipInvoice $invoice): array => $this->payload($invoice, $symbol))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function summary(BillingSummary $summary, PaymentSettings $settings): JsonResponse
    {
        $symbol = (string) $settings->all()['currency_symbol'];
        $figures = $summary->forChamber();

        return response()->json(['data' => $figures + [
            'outstanding_label' => Money::format($figures['outstanding_cents'], $symbol),
            'collected_month_label' => Money::format($figures['collected_month_cents'], $symbol),
            'collected_total_label' => Money::format($figures['collected_total_cents'], $symbol),
        ]]);
    }

    /**
     * Issues one fee to every member it applies to, for one year. Safe to repeat: existing invoices are skipped.
     */
    public function generate(GenerateInvoicesRequest $request, InvoiceService $invoices): JsonResponse
    {
        $fee = FeeType::query()->findOrFail($request->integer('fee_type_id'));
        $result = $invoices->generate($fee, (string) $request->input('period_type'), $request->input('period'), $request->user());

        return response()->json([
            'message' => __('app.billing.generate_done', $result),
            'data' => $result,
        ]);
    }

    public function waive(Request $request, MembershipInvoice $invoice, InvoiceService $invoices): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'];

        $updated = $invoices->waive($invoice, $request->user(), trim($reason));

        return response()->json(['message' => __('app.billing.waived_done'), 'data' => $this->payload($updated->load(['member', 'feeType', 'payments']), (string) app(PaymentSettings::class)->all()['currency_symbol'])]);
    }

    public function recordPayment(RecordPaymentRequest $request, MembershipInvoice $invoice, PaymentService $payments, PaymentSettings $settings): JsonResponse
    {
        try {
            $payment = $payments->record($invoice, [
                'amount_cents' => Money::toCents((string) $request->validated('amount')),
                'method' => $request->validated('method'),
                'reference' => $request->validated('reference'),
                'paid_on' => $request->validated('paid_on'),
                'note' => $request->validated('note'),
            ], $request->user());
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first(), 'errors' => $exception->errors()], 422);
        }

        return response()->json([
            'message' => __('app.billing.payment_saved', ['receipt' => $payment->receipt_number]),
            'data' => $this->payload($invoice->refresh()->load(['member', 'feeType', 'payments']), (string) $settings->all()['currency_symbol']),
        ], 201);
    }

    private function filterStatus(Builder $query, string $status): Builder
    {
        return match ($status) {
            'overdue' => $query->open()->where('due_on', '<', today()),
            MembershipInvoice::STATUS_UNPAID, MembershipInvoice::STATUS_PARTIAL, MembershipInvoice::STATUS_PAID, MembershipInvoice::STATUS_WAIVED => $query->where('status', $status),
            default => $query,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MembershipInvoice $invoice, string $symbol): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'member' => [
                'id' => $invoice->member->id,
                'member_no' => $invoice->member->member_no,
                'name' => $invoice->member->applicant_name,
                'business' => $invoice->member->business_name,
            ],
            'fee' => ['id' => $invoice->feeType->id, 'name' => $invoice->feeType->name_bm],
            'period' => $invoice->period_key === InvoiceService::LIFETIME_KEY ? null : $invoice->period_key,
            'amount_cents' => $invoice->amount_cents,
            'paid_cents' => $invoice->paid_cents,
            'balance_cents' => $invoice->balanceCents(),
            'amount_label' => Money::format($invoice->amount_cents, $symbol),
            'paid_label' => Money::format($invoice->paid_cents, $symbol),
            'balance_label' => Money::format($invoice->balanceCents(), $symbol),
            'status' => $invoice->status,
            'overdue' => $invoice->isOverdue(),
            'due_on' => $invoice->due_on->toDateString(),
            'issued_at' => $invoice->issued_at->toIso8601String(),
            'waived_reason' => $invoice->waived_reason,
            'payments' => $invoice->payments->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'receipt' => $payment->receipt_number,
                'amount_label' => Money::format($payment->amount_cents, $symbol),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'paid_on' => $payment->paid_on->toDateString(),
                'voided' => $payment->isVoided(),
                'void_reason' => $payment->void_reason,
            ])->values()->all(),
        ];
    }
}
