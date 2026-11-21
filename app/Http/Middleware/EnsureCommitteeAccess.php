<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Member accounts see only their own profile, their votes and their activity. Everything else
 * in the committee area is for committee, admin and superadmin accounts.
 */
class EnsureCommitteeAccess
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

        return redirect()->route('admin.votes.index');
    }
}
