<?php

use App\Domains\Identity\Roles;
use Spatie\Permission\Models\Role;

/**
 * The roles exist on any environment that has run the migrations.
 *
 * The hazard being retired is a deployment that migrates but does not seed.
 * Registration calls assignRole(Roles::MEMBER), and Spatie throws
 * RoleDoesNotExist on a missing row rather than creating one, so that
 * deployment would reject every new member with a 500 while every page that
 * does not touch roles looked healthy.
 */
test('a fresh migration creates all five roles with no seeder', function () {
    // migrate:fresh drops every table and replays the migrations, which is the
    // blank-database case this is about. No seeder is invoked anywhere in it:
    // --seed is not passed, and DatabaseSeeder no longer creates roles.
    $this->artisan('migrate:fresh', ['--no-interaction' => true])->assertExitCode(0);

    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(Roles::all())->sort()->values()->all());
});

test('replaying the migration leaves one row per role', function () {
    // An environment upgrading from the seeder era already has these rows. Any
    // path that recreated them would change their ids and unassign every
    // administrator, because model_has_roles references role ids, not names.
    $before = Role::query()->pluck('id', 'name')->all();

    $migration = require base_path('database/migrations/2026_10_03_200000_create_role_vocabulary.php');
    $migration->up();

    expect(Role::query()->count())->toBe(count(Roles::all()))
        ->and(Role::query()->pluck('id', 'name')->all())->toBe($before);
});
