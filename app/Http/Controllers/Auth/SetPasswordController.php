<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\MemberAccountService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Lets a member choose a password through the set-up link. The link is single use and expires after 72 hours.
 */
class SetPasswordController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.set-password', [
            'token' => $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request, MemberAccountService $accounts, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'password.min' => __('app.validation.password_min'),
            'password.confirmed' => __('app.validation.password_mismatch'),
        ]);

        $status = $accounts->broker()->reset(
            [
                'token' => $validated['token'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $request->input('password_confirmation'),
            ],
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                $user->setRememberToken(null);
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return back()
                ->withInput(['email' => $validated['email']])
                ->withErrors(['token' => __('app.members.account.link_invalid')]);
        }

        $account = User::query()->where('email', strtolower($validated['email']))->first();

        if ($account !== null) {
            $activity->record('profile.password_set', null, $account, $account);
        }

        return redirect()->route('login')->with('status', __('app.members.account.password_set'));
    }
}
