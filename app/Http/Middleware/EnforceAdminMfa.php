<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Roles;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Second factor is not optional for staff.
 *
 * Plan Section 11 requires mandatory TOTP for admins; ADR-0007 puts admin
 * identity on the users table, so the flag read here is
 * users.two_factor_confirmed_at rather than a column on a separate admins
 * table.
 *
 * Attached to the web group rather than to an admin route group, for the same
 * reason RecordAdminWrites is: a route added in a later checkpoint cannot
 * arrive unguarded because somebody forgot to list a middleware. The checks
 * below make it inert everywhere except /admin.
 *
 * What this deliberately does not do is authenticate or authorise. An
 * unauthenticated request passes straight through to the auth middleware, and
 * a member who wanders onto /admin passes through to whatever role gate guards
 * that route. This middleware answers one question only: has this member of
 * staff finished setting up their second factor?
 */
class EnforceAdminMfa
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*')) {
            return $next($request);
        }

        $user = $request->user();

        // Not signed in: the auth middleware owns that answer, not this one.
        if (! $user instanceof User) {
            return $next($request);
        }

        // Not staff: role gates decide whether they belong here at all.
        if (! $user->hasAnyRole(Roles::admin())) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        // The setup page is itself under /admin, so without this the redirect
        // would point at a route that redirects to itself.
        if ($request->routeIs('admin.mfa-setup')) {
            return $next($request);
        }

        return redirect()->route('admin.mfa-setup');
    }
}
