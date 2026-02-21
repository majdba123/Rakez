<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        $counts = SeedCounts::all();
        $admin = User::where('email', 'admin@gmail.com')->first();

        $teamNames = [
            'Alpha Team',
            'Beta Team',
            'Gamma Team',
            'Delta Team',
            'Epsilon Team',
            'Zeta Team',
            'Sigma Team',
            'Omega Team',
        ];

        $limit = min($counts['teams'], count($teamNames));
        for ($i = 0; $i < $limit; $i++) {
            Team::updateOrCreate(
                ['name' => $teamNames[$i]],
                [
                    'description' => 'Primary operations team',
                    'created_by' => $admin?->id,
                ]
            );
        }
    }
}
