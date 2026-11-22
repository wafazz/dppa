<?php

namespace App\Http\Controllers;

use App\Services\BillingSummary;
use App\Services\PaymentSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A member's own charges, payments and how to pay. Members see only their own records.
 */
class MemberBillingController extends Controller
{
    public function index(Request $request, BillingSummary $summary, PaymentSettings $settings): View
    {
        $member = $request->user()->member;
        $symbol = (string) $settings->all()['currency_symbol'];

        return view('member.invoices', [
            'billing' => $member === null ? null : $summary->forMember($member),
            'settings' => $settings->all(),
            'symbol' => $symbol,
        ]);
    }
}
