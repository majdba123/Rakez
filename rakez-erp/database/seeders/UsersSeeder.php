<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $teamIds = Team::pluck('id')->all();
        if (empty($teamIds)) {
            $teamIds = [Team::create(['name' => 'Default Team', 'description' => 'Primary team'])->id];
        }

        $fixedUsers = [
            [
                'name' => 'System Admin',
                'email' => 'admin@rakez.com',
                'type' => 'admin',
                'is_manager' => true,
            ],
            [
                'name' => 'Sales Leader',
                'email' => 'sales.leader@rakez.com',
                'type' => 'sales',
                'is_manager' => true,
            ],
            [
                'name' => 'Sales User',
                'email' => 'sales@rakez.com',
                'type' => 'sales',
                'is_manager' => false,
            ],
            [
                'name' => 'Marketing User',
                'email' => 'marketing@rakez.com',
                'type' => 'marketing',
                'is_manager' => false,
            ],
            [
                'name' => 'HR User',
                'email' => 'hr@rakez.com',
                'type' => 'hr',
                'is_manager' => false,
            ],
            [
                'name' => 'Credit User',
                'email' => 'credit@rakez.com',
                'type' => 'credit',
                'is_manager' => false,
            ],
            [
                'name' => 'Accounting User',
                'email' => 'accounting@rakez.com',
                'type' => 'accounting',
                'is_manager' => false,
            ],
            [
                'name' => 'PM User',
                'email' => 'pm@rakez.com',
                'type' => 'project_management',
                'is_manager' => true,
            ],
            [
                'name' => 'Editor User',
                'email' => 'editor@rakez.com',
                'type' => 'editor',
                'is_manager' => false,
            ],
            [
                'name' => 'Developer User',
                'email' => 'developer@rakez.com',
                'type' => 'developer',
                'is_manager' => false,
            ],
            [
                'name' => 'Default User',
                'email' => 'user@rakez.com',
                'type' => 'user',
                'is_manager' => false,
            ],
        ];

        foreach ($fixedUsers as $userData) {
            $teamId = $userData['type'] === 'admin' ? null : Arr::random($teamIds);
            $attrs = [
                'name' => $userData['name'],
                'password' => Hash::make('password'),
                'type' => $userData['type'],
                'is_manager' => $userData['is_manager'],
                'team_id' => $teamId,
                'phone' => '05' . fake()->numerify('########'),
                'commission_eligibility' => in_array($userData['type'], ['sales', 'marketing'], true),
                'is_active' => true,
            ];
            if ($userData['type'] !== 'admin') {
                $attrs['salary'] = fake()->numberBetween(3000, 20000);
            }
            User::updateOrCreate(
                ['email' => $userData['email']],
                $attrs
            );
        }

        $randomCounts = [
            'sales' => 60,
            'marketing' => 20,
            'hr' => 10,
            'credit' => 10,
            'accounting' => 10,
            'project_management' => 10,
            'editor' => 10,
            'developer' => 10,
            'user' => 34,
        ];

        foreach ($randomCounts as $type => $count) {
            for ($i = 0; $i < $count; $i++) {
                $isManager = $type === 'sales' && $i < 8;
                User::factory()->create([
                    'type' => $type,
                    'is_manager' => $isManager,
                    'team_id' => $teamIds ? Arr::random($teamIds) : null,
                    'commission_eligibility' => in_array($type, ['sales', 'marketing'], true),
                    'is_active' => true,
                    'salary' => $type === 'admin' ? null : fake()->numberBetween(3000, 20000),
                ]);
            }
        }

        User::with('roles')->get()->each(function (User $user) {
            if ($user->roles()->count() === 0) {
                $user->syncRolesFromType();
            }
        });
    }
}
