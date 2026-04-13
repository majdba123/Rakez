<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $exists = User::where('email', 'admin@rakez.com')->exists();

        if (! $exists) {
            User::create([
                'name' => 'System Administrator',
                'email' => 'admin@rakez.com',
                'phone' => '0500000000',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'is_manager' => false,
                'is_active' => true,
            ]);
        }
    }
}
