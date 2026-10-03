<?php

use App\Domains\Identity\SessionVersion;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

/**
 * Changing a password ends every other session.
 *
 * The Maveren audit recorded the gap: a password change or reset there left
 * every existing session alive, because nothing on the server could tell which
 * sessions predated the change. A stolen session survived the single action a
 * member takes in order to end it.
 *
 * A second session is modelled by making a request that carries the version it
 * was stamped with at its own login — which is exactly what a real second
 * browser sends. There is no need to simulate two cookie jars to prove the
 * rule, only to send the stale number.
 */
test('another session is logged out after a password change', function () {
    $user = User::factory()->create(['password' => 'password']);

    expect($user->session_version)->toBe(0);

    // Session A changes the password.
    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->session_version)->toBe(1);

    // Session B still carries version 0 and is turned away on its next request.
    $this->actingAs($user)
        ->withSession([SessionVersion::KEY => 0])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the session that changed the password survives', function () {
    $user = User::factory()->create(['password' => 'password']);

    // A member thrown out by their own password change reads it as a failure,
    // and learns not to rotate passwords.
    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    $this->withSession([SessionVersion::KEY => $user->refresh()->session_version])
        ->get(route('dashboard'))
        ->assertOk();
});

test('a password reset increments the version and invalidates existing sessions', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'reset-password',
            'password_confirmation' => 'reset-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect($user->refresh()->session_version)->toBe(1);

    // Nothing is preserved on a reset: the account may be in someone else's
    // hands, which is why the reset is happening.
    $this->actingAs($user)
        ->withSession([SessionVersion::KEY => 0])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('a fresh login after a password change is stamped with the new version', function () {
    $user = User::factory()->create(['password' => 'password']);

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->session_version)->toBe(1);

    // Signing in fresh goes through the Login event, so the stamp is applied
    // by the listener rather than by the test helper.
    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'new-password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
    expect(session(SessionVersion::KEY))->toBe(1);

    $this->get(route('dashboard'))->assertOk();
});

test('a session that was never stamped is treated as revoked', function () {
    // Adopting the current version here would be convenient and wrong: an
    // unstamped session is the shape an attacker's pre-existing session has,
    // and adopting would let it survive the password change meant to kill it.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession([SessionVersion::KEY => null])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the version survives a database round trip as an integer', function () {
    // The regression this guards: MySQL returns BIGINT as a string under
    // emulated prepares. Without the integer cast on User, the identity
    // comparison in CheckSessionVersion would be '0' === 0, which is false,
    // and every authenticated request in production would be logged out while
    // the test suite stayed green — because an in-memory factory model carried
    // null on both sides and matched itself.
    $user = User::factory()->create();

    $loaded = User::query()->findOrFail($user->id);

    expect($loaded->session_version)->toBeInt()->toBe(0);

    $this->actingAs($loaded)->get(route('dashboard'))->assertOk();
});
