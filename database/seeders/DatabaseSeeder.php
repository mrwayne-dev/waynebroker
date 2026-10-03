<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles are not seeded: the role vocabulary ships as a migration, so
        // every environment that has the schema has the names too.
        //
        // WithoutModelEvents is deliberately not used here. It would suppress
        // UserObserver, and a seeded user with no wallet is a shape the
        // application is built to never see — exactly the state Maveren's lazy
        // creation existed to paper over.

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
