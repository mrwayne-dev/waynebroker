<?php

use App\Domains\Identity\Roles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * The role vocabulary is schema, not sample data.
 *
 * It lived in RoleSeeder until now, which made every environment's correctness
 * depend on someone remembering a second command. Registration assigns
 * Roles::MEMBER strictly — Spatie throws RoleDoesNotExist rather than inventing
 * the row — so a deployment that migrated but did not seed would accept the
 * form, fail on assignRole, and reject every new member with a 500. The five
 * names are a closed set that the application compiles against; they belong
 * with the tables that reference them.
 *
 * Permissions, when they arrive, are a different question: they are policy and
 * may legitimately differ between environments. This migration is deliberately
 * about roles only.
 */
return new class extends Migration
{
    public function up(): void
    {
        // findOrCreate, not create: this migration may run against a database
        // that already has roles from the seeder era, and recreating the rows
        // would change their ids and unassign everybody, since model_has_roles
        // points at role ids rather than names.
        foreach (Roles::all() as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function down(): void
    {
        Role::query()
            ->whereIn('name', Roles::all())
            ->where('guard_name', 'web')
            ->delete();
    }
};
