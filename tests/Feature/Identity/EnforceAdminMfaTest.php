<?php

use App\Domains\Identity\Roles;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Mandatory second factor for staff (plan Section 11).
 *
 * The /admin surface proper arrives in Checkpoint 7, so the routes exercised
 * here are declared in the test. That is the point of landing the guard first:
 * the surface cannot arrive unguarded.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth'])->get('admin/things', fn () => response('ok'))
        ->name('admin.things');
});

function admin(string $role, bool $mfaConfirmed): User
{
    $user = User::factory()->create([
        'two_factor_confirmed_at' => $mfaConfirmed ? now() : null,
    ]);

    $user->assignRole($role);

    return $user;
}

dataset('admin roles', [
    'super admin' => [Roles::SUPER_ADMIN],
    'finance admin' => [Roles::FINANCE_ADMIN],
    'support admin' => [Roles::SUPPORT_ADMIN],
    'content admin' => [Roles::CONTENT_ADMIN],
]);

test('an admin without a confirmed second factor is held at the setup page', function (string $role) {
    $this->actingAs(admin($role, mfaConfirmed: false))
        ->get('admin/things')
        ->assertRedirect(route('admin.mfa-setup'));
})->with('admin roles');

test('an admin with a confirmed second factor passes through', function (string $role) {
    $this->actingAs(admin($role, mfaConfirmed: true))
        ->get('admin/things')
        ->assertOk();
})->with('admin roles');

test('the setup page itself is reachable without a confirmed factor', function () {
    // Without this exemption the redirect target would redirect to itself and
    // the administrator could never enrol.
    $this->actingAs(admin(Roles::SUPER_ADMIN, mfaConfirmed: false))
        ->get(route('admin.mfa-setup'))
        ->assertOk();
});

test('a member is unaffected on their own routes', function () {
    $member = User::factory()->create(['two_factor_confirmed_at' => null]);
    $member->assignRole(Roles::MEMBER);

    $this->actingAs($member)->get('/dashboard')->assertOk();
});

test('a member reaching an admin route is not sent to the admin setup page', function () {
    // Whether they may be here at all is a role gate's answer, not this
    // middleware's. It must not quietly convert an authorisation question into
    // an enrolment prompt.
    $member = User::factory()->create(['two_factor_confirmed_at' => null]);
    $member->assignRole(Roles::MEMBER);

    $this->actingAs($member)->get('admin/things')->assertOk();
});

test('an unauthenticated visitor is left to the auth middleware', function () {
    $this->get('admin/things')->assertRedirect(route('login'));
});
