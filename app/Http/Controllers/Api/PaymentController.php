<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /**
     * A mistaken payment is voided with a reason. It is never deleted.
     */
    public function void(Request $request, Payment $payment, PaymentService $payments): JsonResponse
    {
        $reason = trim((string) $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);

        try {
            $payments->void($payment, $request->user(), $reason);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->validator->errors()->first()], 422);
        }

        return response()->json(['message' => __('app.billing.payment_voided')]);
    }
}
