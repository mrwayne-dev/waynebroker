<?php

namespace App\Http\Middleware;

use App\Domains\Identity\SessionVersion;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends sessions issued under a superseded password.
 *
 * A session carries the users.session_version it was issued under. Changing or
 * resetting the password bumps that column, so every other session is now
 * carrying a stale number and is turned away here on its next request.
 *
 * An unstamped session is treated as stale rather than adopted. Adopting would
 * be the convenient choice and the wrong one: a session that predates this
 * mechanism is exactly the kind an attacker would be holding, and letting it
 * quietly inherit the current version would survive the password change it is
 * supposed to be killed by.
 */
class CheckSessionVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        if (SessionVersion::carried($request) === $user->session_version) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
