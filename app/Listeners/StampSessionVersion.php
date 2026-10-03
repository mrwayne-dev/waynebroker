<?php

namespace App\Listeners;

use App\Domains\Identity\SessionVersion;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Stamps a freshly authenticated session with the user's current version.
 *
 * Hung on the Login event rather than on a controller so that every way into
 * the application is covered by construction — password, passkey, two-factor
 * challenge, and whatever Fortify adds next. A stamp applied in one login
 * controller is a revocation mechanism with a hole in it shaped like every
 * other login path.
 */
class StampSessionVersion
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            SessionVersion::stamp($this->request, $event->user);
        }
    }
}
