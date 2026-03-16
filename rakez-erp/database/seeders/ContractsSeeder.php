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

    protected array $projectNames = [
        'برج الرؤية السكني', 'مجمع النخيل السكني', 'فلل الواحة الفاخرة', 'عمارة الروضة',
        'مشروع الرياض الجديدة', 'مجمع الياسمين', 'برج الإمارة', 'وحدات الملقا السكنية',
        'مشروع النور السكني', 'مجمع الخليج', 'برج الفيصلية', 'عمارات الشفا',
    ];

    protected array $cities = ['الرياض', 'جدة', 'الدمام', 'مكة المكرمة', 'المدينة المنورة', 'الخبر', 'الطائف', 'تبوك'];
    protected array $districts = ['حي الملقا', 'حي النزهة', 'حي الشفا', 'حي العليا', 'حي الروضة', 'حي الياسمين', 'حي النخيل', 'حي الواحة'];

    public function run(): void
    {
        $counts = SeedCounts::all();
        $totalContracts = (int) $counts['contracts'];
        $developerCount = count($this->developerList);

        $statuses = array_merge(
            array_fill(0, 35, 'approved'),
            array_fill(0, 10, 'pending'),
            array_fill(0, 3, 'rejected'),
            array_fill(0, 2, 'completed')
        );
        shuffle($statuses);

        $owners = array_merge(
            User::where('type', 'project_management')->pluck('id')->all(),
            User::where('type', 'admin')->pluck('id')->all()
        );
        if (empty($owners)) {
            $owners = User::limit(1)->pluck('id')->all();
        }

        $teamIds = Team::pluck('id')->all();

        // Ensure at least one contract per developer (so /developers/{developer_number} always finds data)
        for ($i = 0; $i < $totalContracts; $i++) {
            $status = $statuses[$i] ?? 'pending';
            $ownerId = $owners ? Arr::random($owners) : ($owners[0] ?? User::first()?->id);
            $isOffPlan = fake()->boolean(30);
            // First N contracts: one per developer; rest: random developer
            $developer = $i < $developerCount
                ? $this->developerList[$i]
                : Arr::random($this->developerList);

            $realEstateImages = config('unsplash_images.real_estate', []);
            $logoThumbs = config('unsplash_images.logo_thumb', []);
            $projectImage = $realEstateImages ? Arr::random($realEstateImages) : 'https://via.placeholder.com/800x600';
            $logoUrl = $logoThumbs ? Arr::random($logoThumbs) : 'https://via.placeholder.com/150';
            $deptImage = $realEstateImages ? Arr::random($realEstateImages) : 'https://via.placeholder.com/1200x800';

            $contract = Contract::factory()->create([
                'user_id' => $ownerId,
                'project_name' => $this->projectNames[$i % count($this->projectNames)] . ' ' . ($i + 1),
                'developer_name' => $developer['name'],
                'developer_number' => $developer['number'],
                'city' => Arr::random($this->cities),
                'district' => Arr::random($this->districts),
                'status' => $status,
                'is_off_plan' => $isOffPlan,
                'project_image_url' => $projectImage,
                'emergency_contact_number' => '05' . fake()->numerify('########'),
                'security_guard_number' => '05' . fake()->numerify('########'),
                'notes' => 'مشروع سكني ضمن خطة التطوير - وحدات متنوعة وتشطيبات ممتازة.',
                'developer_requiment' => 'متطلبات المطور: جودة عالية في التشطيب والالتزام بمواعيد التسليم.',
                'commission_percent' => fake()->randomFloat(2, 2, 4),
                'commission_from' => 'المالك',
            ]);

            ContractInfo::factory()->create([
                'contract_id' => $contract->id,
            ]);

            $secondParty = SecondPartyData::factory()->create([
                'contract_id' => $contract->id,
                'project_logo_url' => $logoUrl,
            ]);

            BoardsDepartment::create([
                'contract_id' => $contract->id,
                'has_ads' => fake()->boolean(),
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 30)),
            ]);

            PhotographyDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => $deptImage,
                'video_url' => 'https://example.com/video.mp4',
                'description' => 'صور احترافية للوحدة والمعرض - تم المعالجة',
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);

            MontageDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => $realEstateImages ? Arr::random($realEstateImages) : 'https://via.placeholder.com/1200x800',
                'video_url' => 'https://example.com/montage.mp4',
                'description' => 'فيديو مونتاج للمشروع - جاهز للنشر',
                'processed_by' => Arr::random($owners),
                'processed_at' => now()->subDays(fake()->numberBetween(1, 20)),
            ]);

            // Seed contract units: available, reserved, sold only (لا يوجد pending للوحدات)
            $unitsPerContract = $counts['units_per_contract'];
            $unitStatuses = array_merge(
                array_fill(0, (int) ceil($unitsPerContract * 0.5), 'available'),
                array_fill(0, (int) ceil($unitsPerContract * 0.25), 'reserved'),
                array_fill(0, max(1, $unitsPerContract - (int) ceil($unitsPerContract * 0.5) - (int) ceil($unitsPerContract * 0.25)), 'sold')
            );
            shuffle($unitStatuses);

            for ($u = 0; $u < $unitsPerContract; $u++) {
                ContractUnit::factory()->create([
                    'second_party_data_id' => $secondParty->id,
                    'unit_number' => 'U-' . str_pad((string) ($u + 1), 3, '0', STR_PAD_LEFT),
                    'status' => $unitStatuses[$u] ?? 'available',
                ]);
            }

            $mediaImages = config('unsplash_images.real_estate', []);
            for ($m = 0; $m < 3; $m++) {
                ProjectMedia::create([
                    'contract_id' => $contract->id,
                    'type' => $m % 2 === 0 ? 'image' : 'video',
                    'url' => $m % 2 === 0
                        ? ($mediaImages ? Arr::random($mediaImages) : 'https://via.placeholder.com/1000x700')
                        : 'https://example.com/project.mp4',
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
