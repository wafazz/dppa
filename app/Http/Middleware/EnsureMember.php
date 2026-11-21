<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The /member area is for member accounts. Committee, admin and superadmin accounts use /admin.
 */
class EnsureMember
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isMember()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => __('app.validation.admin_area_only')], 403);
        }

        return redirect()->route('admin.dashboard');
    }
}
