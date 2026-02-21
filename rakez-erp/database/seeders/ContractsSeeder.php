<?php

namespace Database\Seeders;

use App\Models\BoardsDepartment;
use App\Models\Contract;
use App\Models\ContractInfo;
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

    public function run(): void
    {
        $counts = SeedCounts::all();

        $statuses = array_merge(
            array_fill(0, 20, 'ready'),
            array_fill(0, 15, 'approved'),
            array_fill(0, 10, 'pending'),
            array_fill(0, 3, 'rejected'),
            array_fill(0, 2, 'completed')
        );
        shuffle($statuses);

        $owners = array_merge(
            User::where('type', 'project_management')->pluck('id')->all(),
            User::where('type', 'admin')->pluck('id')->all()
        );

        $teamIds = Team::pluck('id')->all();

        for ($i = 0; $i < $counts['contracts']; $i++) {
            $status = $statuses[$i] ?? 'pending';
            $ownerId = $owners ? Arr::random($owners) : null;
            $isOffPlan = fake()->boolean(30);
            $developer = Arr::random($this->developerList);

            $contract = Contract::factory()->create([
                'user_id' => $ownerId,
                'status' => $status,
                'is_off_plan' => $isOffPlan,
                'developer_name' => $developer['name'],
                'developer_number' => $developer['number'],
                'project_image_url' => 'https://via.placeholder.com/800x600',
                'emergency_contact_number' => '05' . fake()->numerify('########'),
                'security_guard_number' => '05' . fake()->numerify('########'),
            ]);

            ContractInfo::factory()->create([
                'contract_id' => $contract->id,
            ]);

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

            // Seed contract units with all possible statuses
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

            if ($teamIds) {
                $teamCount = fake()->numberBetween(2, 3);
                $attachIds = Arr::random($teamIds, $teamCount);
                $contract->teams()->syncWithoutDetaching($attachIds);
            }
        }
    }
}
