<?php

use App\Domains\Identity\Roles;
use App\Models\User;
use Symfony\Component\Finder\Finder;

/**
 * Retires Maveren audit finding H-9.
 *
 * Maveren's api/auth/admin_register.php assigned role = 'super_admin' to
 * anybody who posted the shared ADMIN_INVITE_CODE, with a comment in the file
 * acknowledging the consequence: "everyone with that code now gets full
 * control of the deposit addresses members send funds to". A shared secret in
 * .env, not a deliberate grant, decided who could move money.
 *
 * Gotham has no invite-code path at all, so there is nothing to delete. What
 * can be proved is the rule that replaces it: self-service registration grants
 * the least privileged role, and no code path hands out super_admin.
 */

/**
 * Every role granted by an assignRole() call in first-party source.
 *
 * @return array<string, list<string>> relative file path => granted role
 */
function assignedRolesInSource(): array
{
    $found = [];

    $finder = (new Finder)
        ->files()
        ->in([base_path('app'), base_path('database/seeders')])
        ->name('*.php');

    foreach ($finder as $file) {
        $relative = str_replace(base_path().'/', '', $file->getRealPath() ?: '');
        $contents = $file->getContents();

        if (preg_match_all('/->(?:assignRole|syncRoles)\s*\(([^;]*?)\)\s*;/s', $contents, $matches) === 0) {
            continue;
        }

        $granted = [];

        foreach ($matches[1] as $argument) {
            // Roles::SUPER_ADMIN and 'super_admin' are the same grant wearing
            // different clothes, so both forms are collected.
            if (preg_match_all('/Roles::([A-Z_]+)/', $argument, $constants) > 0) {
                foreach ($constants[1] as $constant) {
                    $granted[] = constant(Roles::class.'::'.$constant);
                }
            }

            if (preg_match_all('/[\'"]([a-z_]+)[\'"]/', $argument, $literals) > 0) {
                foreach ($literals[1] as $literal) {
                    $granted[] = $literal;
                }
            }
        }

        if ($granted !== []) {
            $found[$relative] = array_values(array_unique($granted));
        }
    }

    return $found;
}

test('no code path grants super_admin', function () {
    $offenders = [];

    foreach (assignedRolesInSource() as $file => $roles) {
        if (in_array(Roles::SUPER_ADMIN, $roles, true)) {
            $offenders[] = $file;
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['These grant super_admin from application code (Maveren H-9):'],
        $offenders,
        ['A super_admin is granted deliberately by another super_admin, never by a code path.'],
    )));
});

test('registration grants exactly the member role', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'member@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'member@example.test')->sole();

    expect($user->getRoleNames()->all())->toBe([Roles::MEMBER]);
});

test('a registered user holds no admin role', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'member@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'member@example.test')->sole();

    expect($user->hasAnyRole(Roles::admin()))->toBeFalse();
});
