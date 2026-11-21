<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /admin area is for committee, admin and superadmin accounts. Member accounts are sent to /member.
 */
class EnsureNotMember
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isMember()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('app.validation.members_only_pages')], 403);
        }

        return redirect()->route('member.home');
    }
}
