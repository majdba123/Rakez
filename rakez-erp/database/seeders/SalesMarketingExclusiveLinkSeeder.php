<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\ExclusiveProjectRequest;
use App\Models\Lead;
use App\Models\MarketingProject;
use App\Models\MarketingProjectTeam;
use App\Models\SalesProjectAssignment;
use App\Models\SalesTarget;
use App\Models\SecondPartyData;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * بعد {@see ExclusiveProjectCsvScenarioSeeder} و{@see MarketingSeeder} و{@see SalesSeeder}:
 * يربط عقد المشروع الحصري النموذجي بقائد المبيعات، فريقه، مشروع التسويق، وهدف مبيعات صالح
 * (marketer من نوع sales ونفس فريق القائد حسب {@see \App\Services\Sales\SalesTargetService::createTarget}).
 *
 * آمن للتشغيل المتكرر.
 */
class SalesMarketingExclusiveLinkSeeder extends Seeder
{
    /** يجب أن يطابق {@see ExclusiveProjectCsvScenarioSeeder::PROJECT_NAME} */
    private const EXCLUSIVE_PROJECT_NAME = 'مشروع حصري نموذجي (سيدر)';

    public function run(): void
    {
        $request = ExclusiveProjectRequest::query()
            ->where('project_name', self::EXCLUSIVE_PROJECT_NAME)
            ->where('status', 'contract_completed')
            ->whereNotNull('contract_id')
            ->first();

        if (! $request) {
            $this->command?->warn(
                'SalesMarketingExclusiveLinkSeeder: لا يوجد طلب حصري مكتمل بالاسم المتوقع — تم التخطي.'
            );

            return;
        }

        $contract = Contract::query()->find($request->contract_id);
        if (! $contract || $contract->status !== 'completed') {
            $this->command?->warn('SalesMarketingExclusiveLinkSeeder: العقد غير موجود أو غير مكتمل — تم التخطي.');

            return;
        }

        $leader = User::query()->where('email', 'sales.leader@rakez.com')->first();
        $salesStaff = User::query()->where('email', 'sales@rakez.com')->first();
        $marketingStaff = User::query()->where('email', 'marketing@rakez.com')->first();

        if (! $leader || ! $leader->team_id) {
            $this->command?->warn('SalesMarketingExclusiveLinkSeeder: قائد المبيعات أو team_id مفقود — تم التخطي.');

            return;
        }

        if (! $salesStaff) {
            $this->command?->warn('SalesMarketingExclusiveLinkSeeder: sales@rakez.com غير موجود — تم التخطي.');

            return;
        }

        // نفس فريق القائد لموظف المبيعات (مطلوب لـ createTarget)
        if ((int) $salesStaff->team_id !== (int) $leader->team_id) {
            $salesStaff->forceFill(['team_id' => $leader->team_id])->save();
        }

        $teamIds = [(int) $leader->team_id];
        if ($marketingStaff && $marketingStaff->team_id) {
            $teamIds[] = (int) $marketingStaff->team_id;
        }
        $contract->teams()->syncWithoutDetaching(array_unique($teamIds));

        SalesProjectAssignment::updateOrCreate(
            [
                'leader_id' => $leader->id,
                'contract_id' => $contract->id,
            ],
            [
                'assigned_by' => $leader->id,
                'start_date' => now()->subDays(30)->toDateString(),
                'end_date' => now()->addDays(365)->toDateString(),
            ]
        );

        $marketingProject = MarketingProject::query()->firstOrCreate(
            ['contract_id' => $contract->id],
            [
                'status' => 'active',
                'assigned_team_leader' => $leader->id,
            ]
        );
        $marketingProject->forceFill([
            'status' => 'active',
            'assigned_team_leader' => $leader->id,
        ])->save();

        foreach ([$salesStaff, $marketingStaff] as $member) {
            if (! $member) {
                continue;
            }
            MarketingProjectTeam::query()->firstOrCreate(
                [
                    'marketing_project_id' => $marketingProject->id,
                    'user_id' => $member->id,
                ],
                ['role' => 'marketer']
            );
        }

        $secondParty = SecondPartyData::query()->where('contract_id', $contract->id)->first();
        if (! $secondParty) {
            $this->command?->warn('SalesMarketingExclusiveLinkSeeder: لا يوجد طرف ثاني للعقد — تم التخطي بعد التسويق.');

            return;
        }

        $unit = ContractUnit::query()->where('second_party_data_id', $secondParty->id)->orderBy('id')->first();
        if (! $unit) {
            $this->command?->warn('SalesMarketingExclusiveLinkSeeder: لا توجد وحدات للعقد الحصري — تم التخطي.');

            return;
        }

        $target = SalesTarget::query()->updateOrCreate(
            [
                'leader_id' => $leader->id,
                'marketer_id' => $salesStaff->id,
                'contract_id' => $contract->id,
            ],
            [
                'contract_unit_id' => $unit->id,
                'status' => 'in_progress',
                'start_date' => now()->subDays(14)->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
            ]
        );
        $target->contractUnits()->sync([$unit->id]);

        if ($marketingStaff) {
            Lead::query()->firstOrCreate(
                [
                    'project_id' => $contract->id,
                    'assigned_to' => $marketingStaff->id,
                    'contact_info' => 'lead-exclusive-seed@example.com',
                ],
                [
                    'name' => 'عميل تجريبي (مشروع حصري)',
                    'source' => 'organic',
                    'status' => 'new',
                ]
            );
        }

        $this->command?->info(
            'SalesMarketingExclusiveLinkSeeder: تم ربط العقد الحصري #' . $contract->id
                . ' بفريق القائد وهدف المبيعات ومشروع التسويق.'
        );
    }
}
