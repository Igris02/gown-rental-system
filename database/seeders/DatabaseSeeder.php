<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only the sign-in accounts are seeded. Gowns and reservations are created through
     * the admin pages and the customer site.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
        ]);
    }
}
