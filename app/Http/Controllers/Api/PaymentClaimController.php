<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentClaim;
use App\Services\Money;
use App\Services\PaymentClaimService;
use App\Services\PaymentSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentClaimController extends Controller
{
    /**
     * Payments members have reported, waiting for the committee to check against the bank.
     */
    public function index(Request $request, PaymentSettings $settings): JsonResponse
    {
        $symbol = (string) $settings->all()['currency_symbol'];
        $status = $request->string('status', PaymentClaim::STATUS_PENDING)->toString();

        $claims = PaymentClaim::query()
            ->with(['invoice.feeType', 'member'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('created_at')
            ->get()
            ->map(fn (PaymentClaim $claim): array => [
                'id' => $claim->id,
                'status' => $claim->status,
                'invoice_id' => $claim->membership_invoice_id,
                'invoice_number' => $claim->invoice->number,
                'fee' => $claim->invoice->feeType->name_bm,
                'member' => ['name' => $claim->member->applicant_name, 'member_no' => $claim->member->member_no],
                'amount_label' => Money::format($claim->amount_cents, $symbol),
                'method' => $claim->method,
                'reference' => $claim->reference,
                'paid_on' => $claim->paid_on->toDateString(),
                'note' => $claim->note,
                'reject_reason' => $claim->reject_reason,
                'reported_at' => $claim->created_at?->translatedFormat('j M Y, H:i'),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $claims]);
    }

    public function confirm(Request $request, PaymentClaim $claim, PaymentClaimService $claims): JsonResponse
    {
        try {
            $payment = $claims->confirm($claim, $request->user());
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        }

        return response()->json(['message' => __('app.billing.claim_confirmed', ['receipt' => $payment->receipt_number])]);
    }

    public function reject(Request $request, PaymentClaim $claim, PaymentClaimService $claims): JsonResponse
    {
        $reason = trim((string) $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        try {
            $claims->reject($claim, $request->user(), $reason);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        }

        return response()->json(['message' => __('app.billing.claim_rejected')]);
    }
}
