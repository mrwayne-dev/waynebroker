<?php

namespace Tests;

use App\Domains\Identity\SessionVersion;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Sign a user in the way a real login does.
     *
     * actingAs() sets the authenticated user without firing the Login event,
     * so it never stamps the session with a version. CheckSessionVersion
     * treats an unstamped session as revoked — deliberately, since in
     * production every session is stamped at login — which would turn every
     * actingAs() in the suite into an immediate logout.
     *
     * Stamping here keeps the strict production rule and makes the test helper
     * model a real session rather than a half-built one. A test that wants to
     * prove revocation stamps a stale version explicitly.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User) {
            $this->withSession([SessionVersion::KEY => $user->session_version]);
        }

        return $this;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
