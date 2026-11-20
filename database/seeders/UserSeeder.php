<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // The real admin account. Set ADMIN_EMAIL and ADMIN_PASSWORD in .env to change these.
        $this->makeUser(
            name: 'Admin',
            email: env('ADMIN_EMAIL', 'admin@gown.test'),
            password: env('ADMIN_PASSWORD', 'Admin12345'),
            role: 'admin',
        );

        // Test accounts for trying out each role. Skipped on the live site.
        if (! app()->isProduction()) {
            $this->makeUser('Staff Member', 'staff@gown.test', 'Staff12345', 'staff');
            $this->makeUser('Sample Customer', 'customer@gown.test', 'Customer12345', 'customer');
        }

        $this->command?->warn('Seeded users. Change the default admin password before you deploy.');
    }

    /**
     * Creates the user, or updates it if the email already exists,
     * so running the seeder twice never creates duplicates.
     */
    private function makeUser(string $name, string $email, string $password, string $role): void
    {
        $user = User::firstOrNew(['email' => $email]);

        // forceFill is needed because "role" is not mass assignable on the User model.
        $user->forceFill([
            'name' => $name,
            'password' => Hash::make($password),
            'role' => $role,
        ])->save();
    }
}
