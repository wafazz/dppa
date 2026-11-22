<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\FeeType;
use App\Models\Member;
use App\Models\MembershipInvoice;
use App\Models\PaymentClaim;
use App\Services\BillingSummary;
use App\Services\InvoiceService;
use App\Services\Money;
use App\Services\PaymentClaimService;
use App\Services\PaymentSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * A member's own charges. A member chooses which period of a fee to pay, pays it by bank transfer or cash, and
 * reports the payment so the committee can confirm it. Members see only their own records.
 */
class MemberBillingController extends Controller
{
    public function index(Request $request, BillingSummary $summary, PaymentSettings $settings, InvoiceService $invoices): View
    {
        $member = $request->user()->member;
        $values = $settings->all();
        $symbol = (string) $values['currency_symbol'];

        if ($member === null) {
            return view('member.invoices', ['billing' => null, 'settings' => $values, 'symbol' => $symbol, 'choices' => [], 'methods' => []]);
        }

        $billing = $summary->forMember($member);
        $pending = PaymentClaim::query()->pending()->where('member_id', $member->id)->pluck('membership_invoice_id')->all();

        $billing['invoices'] = collect($billing['invoices'])->map(function (array $invoice) use ($pending): array {
            $invoice['can_report'] = $invoice['balance_cents'] > 0 && ! in_array($invoice['status'], ['waived'], true);
            $invoice['pending_report'] = in_array($invoice['id'], $pending, true);

            return $invoice;
        })->all();

        return view('member.invoices', [
            'billing' => $billing,
            'settings' => $values,
            'symbol' => $symbol,
            'choices' => $this->choicesFor($member, $invoices, $symbol),
            'methods' => array_values(array_filter($settings->enabledMethods(), fn (string $method): bool => $method !== 'online')),
        ]);
    }

    /**
     * The member picks a fee and a period to pay. The invoice is created at that period's price.
     */
    public function choose(Request $request, InvoiceService $invoices): RedirectResponse
    {
        $member = $this->approvedMember($request);

        // "feeId|period|key", for example "3|monthly|2027-03".
        $validated = $request->validate([
            'choice' => ['required', 'string', 'regex:/^\d+\|(monthly|yearly|lifetime)\|[0-9a-z-]+$/'],
        ]);

        [$feeId, $periodType, $periodKey] = explode('|', $validated['choice'], 3);
        $fee = FeeType::query()->active()->findOrFail((int) $feeId);

        try {
            $invoices->chooseForMember($member, $fee, $periodType, $periodKey);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', __('app.billing.member.chosen'));
    }

    /**
     * The member reports a payment they made against one of their own invoices.
     */
    public function report(Request $request, MembershipInvoice $invoice, PaymentClaimService $claims): RedirectResponse
    {
        $member = $this->approvedMember($request);

        if ($invoice->member_id !== $member->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validate([
            'amount' => ['required', 'string', 'max:14'],
            'method' => ['required', 'in:cash,bank_transfer,cheque'],
            'reference' => ['nullable', 'string', 'max:120'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $cents = Money::toCents($validated['amount']);

        if ($cents === null) {
            return back()->withErrors(['amount' => __('app.billing.error_amount')])->withInput();
        }

        try {
            $claims->submit($invoice, [
                'amount_cents' => $cents,
                'method' => $validated['method'],
                'reference' => $validated['reference'] ?? null,
                'paid_on' => $validated['paid_on'],
                'note' => $validated['note'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return back()->with('status', __('app.billing.member.reported'));
    }

    private function approvedMember(Request $request): Member
    {
        $member = $request->user()->member;

        abort_if($member === null || $member->status !== MemberStatus::Approved, Response::HTTP_FORBIDDEN);

        return $member;
    }

    /**
     * The fees this member can pay, each with the periods on offer and their prices.
     *
     * @return array<int, array<string, mixed>>
     */
    private function choicesFor(Member $member, InvoiceService $invoices, string $symbol): array
    {
        return FeeType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (FeeType $fee): bool => $invoices->appliesTo($fee, $member))
            ->map(fn (FeeType $fee): array => [
                'id' => $fee->id,
                'name' => $fee->name_bm,
                'options' => collect($invoices->memberPeriodOptions($fee))->map(fn (array $option): array => [
                    'value' => $option['period_type'].'|'.$option['period_key'],
                    'label' => $option['label'].' — '.Money::format((int) $fee->priceFor($option['period_type']), $symbol),
                ])->values()->all(),
            ])
            ->filter(fn (array $choice): bool => $choice['options'] !== [])
            ->values()
            ->all();
    }
}
