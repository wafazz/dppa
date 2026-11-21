<?php

namespace App\Http\Controllers\Auth;

/**
 * Keys used to carry a password-verified account through the second sign-in step.
 */
final class TwoFactorChallenge
{
    public const SESSION_KEY = 'two_factor.pending';

    public const METHOD_KEY = 'two_factor.method';

    public const MAX_ATTEMPTS = 5;
}
