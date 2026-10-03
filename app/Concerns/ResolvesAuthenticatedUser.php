<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * Narrows a form request's actor from User|null to User.
 *
 * Every route backed by these requests sits behind the auth middleware, so the
 * null branch is unreachable in practice. It is written out rather than
 * asserted away because PHPStan is right that the framework's signature permits
 * null: a static-analysis bar is only worth having if the code answers it
 * honestly. An @var annotation or a cast here would hide exactly the class of
 * mistake the bar exists to catch.
 */
trait ResolvesAuthenticatedUser
{
    /**
     * @throws AuthenticationException when the route is reached unauthenticated
     */
    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
