<?php

namespace App\Domains\Identity;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Where a session records which generation of credentials it was issued under.
 *
 * One place, because the value is written from three (login, password change,
 * password reset) and read from one (CheckSessionVersion), and a key spelled
 * differently in any of them would silently disable revocation rather than
 * break anything loudly.
 */
class SessionVersion
{
    public const KEY = 'auth.session_version';

    /** Stamp the current session with the user's present version. */
    public static function stamp(Request $request, User $user): void
    {
        if ($request->hasSession()) {
            $request->session()->put(self::KEY, $user->session_version);
        }
    }

    /** The version this session was issued under, or null if never stamped. */
    public static function carried(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $value = $request->session()->get(self::KEY);

        return is_int($value) ? $value : null;
    }

    /**
     * Revoke every session this user holds, then keep the current one alive.
     *
     * Called when the password changes. The member doing the changing should
     * not be thrown out by their own action — that reads as a failure and
     * teaches people not to rotate passwords — so their session is re-stamped
     * with the new version while every other one dies on its next request.
     */
    public static function revokeOthers(Request $request, User $user): void
    {
        $user->forceFill(['session_version' => $user->session_version + 1])->save();

        self::stamp($request, $user);
    }
}
