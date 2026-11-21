<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\EmailNotices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Sends a password reset link by e-mail. The answer is the same whether or not the address has an account,
 * so the form cannot be used to find out who has one.
 */
class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, EmailNotices $notices, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $user = User::query()
            ->where('email', Str::lower(trim($validated['email'])))
            ->where('is_active', true)
            ->first();

        if ($user !== null) {
            $token = Password::broker()->createToken($user);

            $url = route('password.setup', ['token' => $token, 'email' => $user->email]);

            $notices->deliver('password_reset', (string) $user->email, [
                'name' => $user->name,
                'hours' => 72,
            ], $url);

            $activity->record('auth.password_reset_requested', 'e-mailed', $user, $user, ['channel' => 'email']);
        }

        return back()->with('status', __('app.auth.reset_sent'));
    }
}
