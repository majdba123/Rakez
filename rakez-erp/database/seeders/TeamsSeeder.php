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
        $admin = User::where('email', 'admin@rakez.com')->first();
        if (! $admin) {
            $admin = User::where('type', 'admin')->first();
        }

        $teamNames = [
            ['name' => 'فريق النخبة', 'description' => 'فريق المبيعات الرئيسي - منطقة الرياض'],
            ['name' => 'فريق الريادة', 'description' => 'فريق المبيعات - منطقة مكة المكرمة'],
            ['name' => 'فريق التميز', 'description' => 'فريق المبيعات - المنطقة الشرقية'],
            ['name' => 'فريق الإنجاز', 'description' => 'فريق التسويق والمتابعة'],
            ['name' => 'فريق الطموح', 'description' => 'فريق المبيعات - الفرع الثاني'],
            ['name' => 'فريق النجاح', 'description' => 'فريق العمل الميداني'],
            ['name' => 'فريق الأمانة', 'description' => 'فريق المتابعة والعمليات'],
            ['name' => 'فريق التأسيس', 'description' => 'فريق المشاريع الجديدة'],
        ];

        $limit = min($counts['teams'], count($teamNames));
        for ($i = 0; $i < $limit; $i++) {
            Team::updateOrCreate(
                ['name' => $teamNames[$i]['name']],
                [
                    'description' => $teamNames[$i]['description'],
                    'created_by' => $admin?->id,
                ]
            );
        }
    }
}
