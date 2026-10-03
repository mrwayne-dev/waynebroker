<?php

use App\Domains\Identity\Roles;
use Spatie\Permission\Models\Role;
use Symfony\Component\Finder\Finder;

/**
 * Retires Maveren audit finding H-8.
 *
 * In Maveren, `admins.role` was ENUM('super_admin','manager','support') while
 * api/admin/kyc.php and kyc_file.php gated on the constant ROLE_SUPPORT_ADMIN,
 * whose value was the string 'support_admin'. No row could ever hold that
 * value. The gate failed closed and said nothing, so KYC review was unreachable
 * for every role but super_admin and nobody found out from the code.
 *
 * This test reads the source the way a reviewer cannot be relied on to: it
 * finds every place a role is named by string literal and asserts the string is
 * in the vocabulary. It passes trivially today because no gate exists yet. That
 * is the point — it is in place before the first gate lands in Checkpoint 7, so
 * the drift can never open.
 */

/**
 * Role names written as string literals anywhere in first-party source.
 *
 * @return array<string, list<string>> relative file path => literals found
 */
function roleLiteralsInSource(): array
{
    $patterns = [
        // Spatie's role API: hasRole('x'), assignRole('x'), syncRoles([...])
        '/\b(?:hasRole|hasAnyRole|hasAllRoles|hasExactRoles|assignRole|removeRole|syncRoles)\s*\(([^)]*)\)/i',
        // Route middleware: ->middleware('role:finance_admin|super_admin')
        '/[\'"]role:([^\'"]+)[\'"]/',
        // Blade: @role('x'), @hasanyrole('x|y')
        '/@(?:role|hasrole|hasanyrole|hasallroles)\s*\(([^)]*)\)/i',
        // Direct lookups: Role::findByName('x')
        '/Role::(?:findByName|findOrCreate)\s*\(([^,)]*)/',
    ];

    // The vocabulary itself and this test are where the literals legitimately
    // live; everything else must go through the constants.
    $exempt = [
        'app/Domains/Identity/Roles.php',
        'tests/Feature/Identity/RolesReferencesRealRoleTest.php',
    ];

    $found = [];

    $finder = (new Finder)
        ->files()
        ->in([base_path('app'), base_path('routes'), base_path('resources/views')])
        ->name(['*.php', '*.blade.php']);

    foreach ($finder as $file) {
        $relative = str_replace(base_path().'/', '', $file->getRealPath() ?: '');

        if (in_array($relative, $exempt, true)) {
            continue;
        }

        $contents = $file->getContents();
        $literals = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $argument) {
                // Only quoted arguments are literals. hasRole(Roles::MEMBER)
                // is the shape we want and yields nothing here.
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $argument, $quoted);

                foreach ($quoted[1] as $value) {
                    // 'role:a|b' and @hasanyrole('a|b') carry several names.
                    foreach (explode('|', $value) as $name) {
                        $name = trim($name);

                        if ($name !== '') {
                            $literals[] = $name;
                        }
                    }
                }
            }
        }

        if ($literals !== []) {
            $found[$relative] = array_values(array_unique($literals));
        }
    }

    return $found;
}

test('every role named in source is a real role', function () {
    $offenders = [];

    foreach (roleLiteralsInSource() as $file => $literals) {
        foreach ($literals as $literal) {
            if (! in_array($literal, Roles::all(), true)) {
                $offenders[] = "{$file}: '{$literal}'";
            }
        }
    }

    expect($offenders)->toBe([], implode("\n", array_merge(
        ['These gates name a role that does not exist (Maveren H-8):'],
        $offenders,
        ['Use a App\Domains\Identity\Roles constant.'],
    )));
});

test('the migrations create exactly the role vocabulary', function () {
    // Nothing in this test, and nothing in Pest.php, creates a role. The rows
    // are here because the migrations ran, which is the whole claim.
    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Roles::all())->sort()->values()->all());
});
