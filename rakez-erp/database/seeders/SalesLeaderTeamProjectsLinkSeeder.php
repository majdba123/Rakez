<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * يربط فريق {@see UsersSeeder} لقائد المبيعات الثابت (sales.leader@rakez.com)
 * بعدة عقود مكتملة عبر جدول contract_team، حتى يظهر لهم محتوى في
 * GET /api/sales/team/projects (انظر {@see \App\Services\Sales\SalesProjectService::baseTeamProjectsQuery}).
 *
 * آمن للتشغيل المتكرر (syncWithoutDetaching). يمكن تشغيله وحده على قاعدة قديمة:
 * `php artisan db:seed --class=SalesLeaderTeamProjectsLinkSeeder`
 */
class SalesLeaderTeamProjectsLinkSeeder extends Seeder
{
    public function run(): void
    {
        $leader = User::query()->where('email', 'sales.leader@rakez.com')->first();
        if (! $leader || ! $leader->team_id) {
            $this->command?->warn('SalesLeaderTeamProjectsLinkSeeder: لا يوجد sales.leader@rakez.com أو team_id — تم التخطي.');

            return;
        }

        $teamId = (int) $leader->team_id;

        $contracts = Contract::query()
            ->where('status', 'completed')
            ->orderBy('id')
            ->limit(5)
            ->get();

        if ($contracts->isEmpty()) {
            $this->command?->warn('SalesLeaderTeamProjectsLinkSeeder: لا توجد عقود بحالة completed — تم التخطي.');

            return;
        }

        foreach ($contracts as $contract) {
            $contract->teams()->syncWithoutDetaching([$teamId]);
        }

        $this->command?->info(
            'SalesLeaderTeamProjectsLinkSeeder: تم ربط فريق القائد بـ ' . $contracts->count() . ' عقد(ات) مكتملة.'
        );
    }
}
