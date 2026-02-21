<?php

namespace Database\Seeders;

use App\Models\BoardsDepartment;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractPreparationStage;
use App\Models\ContractUnit;
use App\Models\MontageDepartment;
use App\Models\PhotographyDepartment;
use App\Models\ProjectMedia;
use App\Models\SecondPartyData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class ContractsSeeder extends Seeder
{
    /**
     * Fixed list of developers so multiple contracts share the same developer
     * (realistic for Developers Management view).
     */
    protected array $developerList = [
        ['name' => 'شركة الراجحي للتطوير العقاري', 'number' => '+966112345001'],
        ['name' => 'مؤسسة دار الأركان', 'number' => '+966112345002'],
        ['name' => 'مجموعة إعمار العقارية', 'number' => '+966112345003'],
        ['name' => 'شركة المملكة القابضة', 'number' => '+966112345004'],
        ['name' => 'شركة أبراج البناء', 'number' => '+966112345005'],
        ['name' => 'مؤسسة المدى للتطوير', 'number' => '+966112345006'],
        ['name' => 'شركة النخيل العقارية', 'number' => '+966112345007'],
        ['name' => 'مجموعة العليان', 'number' => '+966112345008'],
    ];

    /**
     * Segment distribution for project management tabs:
     * - unready: pending + approved + rejected (مشاريع غير جاهزة)
     * - ready_for_marketing: ready (مشاريع جاهزة للتسويق)
     * - archive: completed + soft-deleted (الأرشيف)
     * Guarantees minimum counts per tab so all tabs have data to test.
     * Ready-for-marketing: 20 projects (مشاريع جاهزة للتسويق).
     */
    protected function getStatusDistribution(int $total): array
    {
        $minReady = 20;
        $minArchive = 5;
        $rest = $total - $minReady - $minArchive;
        if ($rest < 10) {
            $minReady = max(5, (int) round($total * 0.25));
            $minArchive = max(2, (int) round($total * 0.10));
            $rest = $total - $minReady - $minArchive;
        }

        $pending = (int) round($rest * 0.45);
        $approved = (int) round($rest * 0.40);
        $rejected = max(1, $rest - $pending - $approved);

        return array_merge(
            array_fill(0, $pending, 'pending'),
            array_fill(0, $approved, 'approved'),
            array_fill(0, $rejected, 'rejected'),
            array_fill(0, $minReady, 'ready'),
            array_fill(0, $minArchive, 'completed')
        );
    }

    public function run(): void
    {
        $counts = SeedCounts::all();
        $totalContracts = $counts['contracts'];

        $statuses = $this->getStatusDistribution($totalContracts);
        shuffle($statuses);

        $owners = array_merge(
            User::where('type', 'project_management')->pluck('id')->all(),
            User::where('type', 'admin')->pluck('id')->all()
        );
        if (empty($owners)) {
            $owners = User::limit(3)->pluck('id')->all();
        }

        $teamIds = Team::pluck('id')->all();

        $numToArchive = max(0, min(5, (int) round($totalContracts * 0.06)));

        for ($i = 0; $i < $totalContracts; $i++) {
            $status = $statuses[$i] ?? 'pending';
            $ownerId = $owners ? Arr::random($owners) : null;
            $isOffPlan = fake()->boolean(30);
            $developer = Arr::random($this->developerList);

            $contract = Contract::factory()->create([
                'user_id' => $ownerId,
                'project_name' => 'مشروع ' . ($i + 1) . ' - ' . fake()->words(2, true) . ' ' . $i,
                'status' => $status,
                'is_off_plan' => $isOffPlan,
                'developer_name' => $developer['name'],
                'developer_number' => $developer['number'],
                'project_image_url' => 'https://via.placeholder.com/800x600',
                'emergency_contact_number' => '05' . fake()->numerify('########'),
                'security_guard_number' => '05' . fake()->numerify('########'),
            ]);

            $info = ContractInfo::factory()->create([
                'contract_id' => $contract->id,
            ]);

            // Duration: بعض العقود انتهت مهلتها (انتهت المهلة) وبعضها لا يزال نشطاً (عرض التفاصيل / خلال X أيام)
            $durationScenario = $i % 5; // 0: expired, 1: last day, 2: short remaining, 3,4: normal
            if ($durationScenario === 0) {
                $info->created_at = now()->subDays(fake()->numberBetween(60, 120));
                $info->agreement_duration_days = fake()->numberBetween(30, 50);
                $info->save();
            } elseif ($durationScenario === 1) {
                $info->created_at = now()->subDays(89);
                $info->agreement_duration_days = 90;
                $info->save();
            } elseif ($durationScenario === 2) {
                $info->created_at = now()->subDays(10);
                $info->agreement_duration_days = 30;
                $info->save();
            }

            $secondParty = SecondPartyData::factory()->create([
                'contract_id' => $contract->id,
                'project_logo_url' => 'https://via.placeholder.com/150',
            ]);

            BoardsDepartment::create([
                'contract_id' => $contract->id,
                'has_ads' => fake()->boolean(),
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            ]);

            PhotographyDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => 'https://via.placeholder.com/1200x800',
                'video_url' => 'https://example.com/video.mp4',
                'description' => fake()->sentence(),
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);

            MontageDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => 'https://via.placeholder.com/1200x800',
                'video_url' => 'https://example.com/montage.mp4',
                'description' => fake()->sentence(),
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);

            // مراحل الإعداد (تقدم الإعداد): 0% إلى 100% بدون تعارض
            $completedStagesCount = $i % (ContractPreparationStage::TOTAL_STAGES + 1); // 0..7
            for ($s = 1; $s <= ContractPreparationStage::TOTAL_STAGES; $s++) {
                $stage = ContractPreparationStage::firstOrCreate(
                    [
                        'contract_id' => $contract->id,
                        'stage_number' => $s,
                    ],
                    [
                        'document_link' => null,
                        'entry_date' => null,
                        'completed_at' => null,
                    ]
                );
                if ($s <= $completedStagesCount) {
                    $stage->update([
                        'document_link' => 'https://example.com/stage-' . $s,
                        'entry_date' => now()->subDays(ContractPreparationStage::TOTAL_STAGES - $s),
                        'completed_at' => now()->subDays(ContractPreparationStage::TOTAL_STAGES - $s),
                    ]);
                }
            }

            // وحدات العقود: كل الحالات (متاحة، قيد الانتظار، محجوزة، مباعة)
            $unitsPerContract = $counts['units_per_contract'];
            $unitStatuses = array_merge(
                array_fill(0, (int) ceil($unitsPerContract * 0.4), 'available'),
                array_fill(0, (int) ceil($unitsPerContract * 0.3), 'pending'),
                array_fill(0, (int) ceil($unitsPerContract * 0.2), 'reserved'),
                array_fill(0, max(1, $unitsPerContract - (int) ceil($unitsPerContract * 0.4) - (int) ceil($unitsPerContract * 0.3) - (int) ceil($unitsPerContract * 0.2)), 'sold')
            );
            shuffle($unitStatuses);

            for ($u = 0; $u < $unitsPerContract; $u++) {
                ContractUnit::factory()->create([
                    'second_party_data_id' => $secondParty->id,
                    'status' => $unitStatuses[$u] ?? 'available',
                ]);
            }

            for ($m = 0; $m < 3; $m++) {
                ProjectMedia::create([
                    'contract_id' => $contract->id,
                    'type' => $m % 2 === 0 ? 'image' : 'video',
                    'url' => $m % 2 === 0 ? 'https://via.placeholder.com/1000x700' : 'https://example.com/project.mp4',
                    'department' => $m % 2 === 0 ? 'photography' : 'montage',
                ]);
            }

            // الفرق: جزء بدون فريق (غير معين) وجزء مع 1–3 فرق
            if ($teamIds && ($i % 4) !== 0) {
                $teamCount = ($i % 4 === 1) ? 1 : fake()->numberBetween(2, 3);
                $attachIds = array_values(Arr::random($teamIds, min($teamCount, count($teamIds))));
                $contract->teams()->syncWithoutDetaching($attachIds);
            }

            // أرشفة (soft delete) آخر عدد من العقود لظهورهم في تبويب الأرشيف
            if ($numToArchive > 0 && $i >= $totalContracts - $numToArchive) {
                $contract->delete();
            }
        }
    }
}
