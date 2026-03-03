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
                'name' => 'مدير النظام',
                'email' => 'admin@rakez.com',
                'phone' => '0500000000',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'is_active' => true,
            ]);
        }
    }
}
