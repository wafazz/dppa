<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IntegrationSettings;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The signed-in account's own two-factor sign-in settings. Turning a method off needs the account password.
 */
class TwoFactorSettingsController extends Controller
{
    public function show(Request $request, IntegrationSettings $settings): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'authenticator' => [
                'enabled' => $user->two_factor_authenticator_confirmed_at !== null,
                'setup_pending' => $user->two_factor_authenticator_secret !== null && $user->two_factor_authenticator_confirmed_at === null,
            ],
            'email' => [
                'enabled' => (bool) $user->two_factor_email_enabled,
                'available' => $settings->isConnected('resend'),
            ],
            'whatsapp' => [
                'enabled' => (bool) $user->two_factor_whatsapp_enabled,
                'available' => $settings->isConnected('onsend'),
            ],
        ]]);
    }

    public function startAuthenticator(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        return $this->guard(fn () => response()->json(['data' => $twoFactor->startAuthenticatorSetup($request->user())]));
    }

    public function confirmAuthenticator(Request $request, TwoFactorService $twoFactor): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        return $this->guard(function () use ($request, $twoFactor, $validated): JsonResponse {
            if (! $twoFactor->confirmAuthenticator($request->user(), $validated['code'])) {
                throw ValidationException::withMessages(['code' => __('app.two_factor.error_code_invalid')]);
            }

            return response()->json(['message' => __('app.two_factor.saved_authenticator')]);
        });
    }

    public function sendEmailCode(Request $request, TwoFactorService $twoFactor, IntegrationSettings $settings): JsonResponse
    {
        if (! $settings->isConnected('resend')) {
            return response()->json(['message' => __('app.two_factor.error_email_unavailable')], 422);
        }

        return $this->guard(function () use ($request, $twoFactor): JsonResponse {
            $twoFactor->sendEmailCode($request->user(), 'enable');

            return response()->json(['message' => __('app.two_factor.email_sent')]);
        });
    }

    public function confirmEmail(Request $request, TwoFactorService $twoFactor, IntegrationSettings $settings): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'digits:6']]);

        if (! $settings->isConnected('resend')) {
            return response()->json(['message' => __('app.two_factor.error_email_unavailable')], 422);
        }

        if (! $twoFactor->verifyEmailCode($request->user(), $validated['code'], 'enable')) {
            throw ValidationException::withMessages(['code' => __('app.two_factor.error_code_invalid')]);
        }

        $twoFactor->enableEmail($request->user());

        return response()->json(['message' => __('app.two_factor.saved_email')]);
    }

    /**
     * Sends a code to the account's email so email codes can be switched off.
     */
    public function sendDisableEmailCode(Request $request, TwoFactorService $twoFactor, IntegrationSettings $settings): JsonResponse
    {
        if (! $request->user()->two_factor_email_enabled) {
            return response()->json(['message' => __('app.two_factor.error_not_on')], 422);
        }

        if (! $settings->isConnected('resend')) {
            return response()->json(['message' => __('app.two_factor.error_email_unavailable')], 422);
        }

        return $this->guard(function () use ($request, $twoFactor): JsonResponse {
            $twoFactor->sendEmailCode($request->user(), 'disable');

            return response()->json(['message' => __('app.two_factor.email_sent')]);
        });
    }

    /**
     * Switches a method off. The code must come from that same method: the authenticator app, or the email or
     * WhatsApp message sent to the account.
     */
    public function disable(Request $request, string $method, TwoFactorService $twoFactor, IntegrationSettings $settings): JsonResponse
    {
        abort_unless(in_array($method, TwoFactorService::METHODS, true), 404);

        $validated = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();

        if (! in_array($method, $twoFactor->enabledMethods($user), true)) {
            return response()->json(['message' => __('app.two_factor.error_not_on')], 422);
        }

        if (! $twoFactor->channelConnected($method)) {
            return response()->json(['message' => __('app.two_factor.error_channel_down')], 422);
        }

        $valid = match ($method) {
            'authenticator' => $twoFactor->verifyAuthenticator($user, $validated['code']),
            'email' => $twoFactor->verifyEmailCode($user, $validated['code'], 'disable'),
            default => false,
        };

        if (! $valid) {
            throw ValidationException::withMessages(['code' => __('app.two_factor.error_code_invalid')]);
        }

        $twoFactor->disableMethod($user, $method);

        return response()->json(['message' => __('app.two_factor.disabled')]);
    }

    private function guard(callable $action): JsonResponse
    {
        try {
            return $action();
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
