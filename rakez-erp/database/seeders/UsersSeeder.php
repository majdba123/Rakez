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
            $teamIds = [Team::create([
                'code' => 'RKF-DEFAULT',
                'name' => 'الفريق الافتراضي',
                'description' => 'الفريق الرئيسي للعمليات',
            ])->id];
        }

        $fixedUsers = [
            // [
            //     'name' => 'مدير النظام',
            //     'email' => 'admin@rakez.com',
            //     'type' => 'admin',
            //     'is_manager' => true,
            // ],
            [
                'name' => 'قائد المبيعات',
                'email' => 'sales.leader@rakez.com',
                'type' => 'sales',
                'is_manager' => true,
            ],
            [
                'name' => 'موظف المبيعات',
                'email' => 'sales@rakez.com',
                'type' => 'sales',
                'is_manager' => false,
            ],
            [
                'name' => 'موظف التسويق',
                'email' => 'marketing@rakez.com',
                'type' => 'marketing',
                'is_manager' => false,
            ],
            [
                'name' => 'موظف الموارد البشرية',
                'email' => 'hr@rakez.com',
                'type' => 'hr',
                'is_manager' => false,
            ],
            [
                'name' => 'موظف الائتمان',
                'email' => 'credit@rakez.com',
                'type' => 'credit',
                'is_manager' => false,
            ],
            [
                'name' => 'موظف المحاسبة',
                'email' => 'accounting@rakez.com',
                'type' => 'accounting',
                'is_manager' => false,
            ],
            [
                'name' => 'مدير المشاريع',
                'email' => 'pm@rakez.com',
                'type' => 'project_management',
                'is_manager' => true,
            ],
            [
                'name' => 'موظف المونتاج',
                'email' => 'editor@rakez.com',
                'type' => 'editor',
                'is_manager' => false,
            ],
            [
                'name' => 'موظف التطوير',
                'email' => 'developer@rakez.com',
                'type' => 'developer',
                'is_manager' => false,
            ],
            [
                'name' => 'مستخدم افتراضي',
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
            } else {
                $attrs['salary'] = 0;
            }
            User::updateOrCreate(
                ['email' => $userData['email']],
                $attrs
            );
        }

        // فريق واحد لقائد المبيعات وموظف المبيعات والتسويق (عروض تجريبية وربط عقد/أهداف بدون تعارض فريق)
        $salesLeader = User::query()->where('email', 'sales.leader@rakez.com')->first();
        if ($salesLeader && $salesLeader->team_id) {
            User::query()
                ->whereIn('email', ['sales@rakez.com', 'marketing@rakez.com'])
                ->update(['team_id' => $salesLeader->team_id]);
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

        $arabicFirstNames = ['محمد', 'أحمد', 'خالد', 'عبدالله', 'سعد', 'فهد', 'سارة', 'نورة', 'هند', 'لمى', 'ماجد', 'راشد', 'منى', 'عبير', 'ريم'];
        $arabicLastNames = ['الغامدي', 'الزهراني', 'العتيبي', 'الدوسري', 'القحطاني', 'الشمري', 'الحربي', 'المطيري', 'السعيد', 'العمري'];

        foreach ($randomCounts as $type => $count) {
            for ($i = 0; $i < $count; $i++) {
                $isManager = $type === 'sales' && $i < 8;
                $name = $arabicFirstNames[array_rand($arabicFirstNames)] . ' ' . $arabicLastNames[array_rand($arabicLastNames)];
                User::factory()->create([
                    'name' => $name,
                    'type' => $type,
                    'is_manager' => $isManager,
                    'team_id' => $teamIds ? Arr::random($teamIds) : $teamIds[0] ?? null,
                    'commission_eligibility' => in_array($type, ['sales', 'marketing'], true),
                    'is_active' => true,
                    'salary' => in_array($type, ['admin'], true) ? 0 : fake()->numberBetween(3000, 20000),
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
