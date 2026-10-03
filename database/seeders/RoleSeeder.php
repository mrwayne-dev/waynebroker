<?php

namespace Database\Seeders;

use App\Domains\Identity\Roles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Create every role in the vocabulary.
     *
     * Idempotent: findOrCreate means re-seeding an environment that already has
     * roles assigned to people leaves those assignments intact. A seeder that
     * dropped and recreated rows here would silently unassign every admin,
     * since model_has_roles points at role ids.
     */
    public function run(): void
    {
        foreach (Roles::all() as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
