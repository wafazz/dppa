<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the committee login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Check the password. Accounts with two-factor sign-in continue to the second step before they are signed in.
     */
    public function store(LoginRequest $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $provider = Auth::getProvider();
        $user = $provider->retrieveByCredentials($credentials);

        if ($user === null || ! $provider->validateCredentials($user, $credentials)) {
            app(ActivityLogger::class)->record('auth.login_failed', $credentials['email'], null, null, ['email' => $credentials['email']]);

            return back()
                ->withErrors(['email' => 'Emel atau kata laluan tidak sepadan.'])
                ->onlyInput('email');
        }

        if (! $user->is_active) {
            app(ActivityLogger::class)->record('auth.login_blocked', null, $user, $user);

            return back()
                ->withErrors(['email' => 'Akaun anda telah dinyahaktifkan. Hubungi pentadbir.'])
                ->onlyInput('email');
        }

        $remember = $request->boolean('remember');

        if ($twoFactor->enabledMethods($user) === []) {
            return $this->signIn($request, $user, $remember);
        }

        // Fail closed: a two-factor account never falls back to password-only sign-in.
        if ($twoFactor->usableMethods($user) === []) {
            return back()
                ->withErrors(['email' => __('app.two_factor.error_no_method')])
                ->onlyInput('email');
        }

        app(ActivityLogger::class)->record('auth.two_factor_challenge', null, $user, $user);

        $request->session()->regenerate();
        $request->session()->put(TwoFactorChallenge::SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'attempts' => 0,
        ]);

        return redirect()->route('login.two-factor');
    }

    /**
     * Log the committee user out and invalidate the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Signs the account in. Used by the password step and by the second step.
     */
    public static function signIn(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}
