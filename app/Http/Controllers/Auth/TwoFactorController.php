<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class TwoFactorController extends Controller
{
    /**
     * The second sign-in step: the account chooses a method that is switched on for it.
     */
    public function show(Request $request, TwoFactorService $twoFactor): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $method = $request->session()->get(TwoFactorChallenge::METHOD_KEY);

        return view('auth.two-factor', [
            'methods' => $twoFactor->usableMethods($user),
            'selected' => $method,
            'emailCodeSent' => $method === 'email' && $twoFactor->hasPendingEmailCode($user, 'sign-in'),
        ]);
    }

    /**
     * The account picks one of its methods. Choosing email sends the code.
     */
    public function choose(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'method' => ['required', 'string', 'in:'.implode(',', $twoFactor->usableMethods($user))],
        ]);

        $request->session()->put(TwoFactorChallenge::METHOD_KEY, $validated['method']);
        app(ActivityLogger::class)->record('auth.two_factor_method', $validated['method'], $user, $user, ['method' => $validated['method']]);

        if ($validated['method'] === 'email') {
            try {
                $twoFactor->sendEmailCode($user, 'sign-in');
            } catch (RuntimeException $exception) {
                return back()->withErrors(['method' => $exception->getMessage()]);
            }
        }

        return redirect()->route('login.two-factor');
    }

    /**
     * Checks the code for the chosen method and, when it is right, signs the account in.
     */
    public function verify(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $method = $request->session()->get(TwoFactorChallenge::METHOD_KEY);

        if (! in_array($method, $twoFactor->usableMethods($user), true)) {
            return back()->withErrors(['code' => __('app.two_factor.error_choose_method')]);
        }

        $valid = match ($method) {
            'authenticator' => $twoFactor->verifyAuthenticator($user, $validated['code']),
            'email' => $twoFactor->verifyEmailCode($user, $validated['code'], 'sign-in'),
            default => false,
        };

        if ($valid) {
            $remember = (bool) ($request->session()->get(TwoFactorChallenge::SESSION_KEY)['remember'] ?? false);

            $request->session()->forget([TwoFactorChallenge::SESSION_KEY, TwoFactorChallenge::METHOD_KEY]);

            return LoginController::signIn($request, $user, $remember);
        }

        return $this->failed($request, $user, __('app.two_factor.error_code_invalid'));
    }

    /**
     * Abandon the second step and go back to the password form.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget([TwoFactorChallenge::SESSION_KEY, TwoFactorChallenge::METHOD_KEY]);

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(TwoFactorChallenge::SESSION_KEY);

        if (! is_array($pending) || ! isset($pending['user_id'])) {
            return null;
        }

        $user = User::query()->find($pending['user_id']);

        return $user !== null && $user->is_active ? $user : null;
    }

    private function failed(Request $request, User $user, string $message): RedirectResponse
    {
        $pending = $request->session()->get(TwoFactorChallenge::SESSION_KEY, []);
        $pending['attempts'] = ($pending['attempts'] ?? 0) + 1;

        app(ActivityLogger::class)->record('auth.two_factor_failed', null, $user, $user);

        if ($pending['attempts'] >= TwoFactorChallenge::MAX_ATTEMPTS) {
            app(ActivityLogger::class)->record('auth.two_factor_locked', null, $user, $user);
            $request->session()->forget([TwoFactorChallenge::SESSION_KEY, TwoFactorChallenge::METHOD_KEY]);

            return redirect()->route('login')->withErrors(['email' => __('app.two_factor.error_too_many')]);
        }

        $request->session()->put(TwoFactorChallenge::SESSION_KEY, $pending);

        return back()->withErrors(['code' => $message]);
    }
}
