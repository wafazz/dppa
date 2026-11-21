<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the language chosen in the session. English is the default.
 */
class SetLocale
{
    public const SUPPORTED = ['en', 'ms'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', config('app.locale'));

        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
