<?php

namespace Database\Seeders;

use App\Models\BoardsDepartment;
use App\Models\City;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\District;
use App\Models\ExclusiveProjectRequest;
use App\Models\MontageDepartment;
use App\Models\PhotographyDepartment;
use App\Models\ProjectMedia;
use App\Models\SecondPartyData;
use App\Models\User;
use App\Services\Contract\ContractUnitService;
use App\Support\ContractCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * سيناريو واقعي: مشروع حصري (طلب → موافقة مدير مشاريع → إكمال عقد) ثم رفع وحدات بـ CSV
 * عبر نفس منطق رفع CSV في {@see ContractUnitService::uploadCsvByContractId}.
 *
 * يعتمد على {@see UsersSeeder} و{@see TeamsSeeder} (مثلاً sales@rakez.com و pm@rakez.com).
 * مكانه في التسلسل: {@see SeedManifest::defaultPipeline()}.
 */
class ExclusiveProjectCsvScenarioSeeder extends Seeder
{
    private const PROJECT_NAME = 'مشروع حصري نموذجي (سيدر)';

    public function run(): void
    {
        $requester = User::where('email', 'sales@rakez.com')->first();
        $pmManager = User::where('email', 'pm@rakez.com')->first();

        if (! $requester || ! $pmManager) {
            $this->command?->warn('ExclusiveProjectCsvScenarioSeeder: يتطلب sales@rakez.com و pm@rakez.com — تم التخطي.');

            return;
        }

        $alreadyDone = ExclusiveProjectRequest::query()
            ->where('project_name', self::PROJECT_NAME)
            ->where('status', 'contract_completed')
            ->whereNotNull('contract_id')
            ->first();

        if ($alreadyDone) {
            $contract = Contract::query()->find($alreadyDone->contract_id);
            $hasUnits = $contract && $contract->secondPartyData
                && $contract->secondPartyData->contractUnits()->exists();

            if ($hasUnits) {
                $this->command?->info('ExclusiveProjectCsvScenarioSeeder: السيناريو مكتمل مسبقاً — تم التخطي.');

                return;
            }
        }

        // إزالة طلبات غير مكتملة بنفس الاسم (مثلاً بعد فشل تشغيل سابق)
        ExclusiveProjectRequest::query()
            ->where('project_name', self::PROJECT_NAME)
            ->where('status', '!=', 'contract_completed')
            ->delete();

        // إنشاء الطلب بأعمدة الجدول الفعلية فقط.
        $request = ExclusiveProjectRequest::create([
            'requested_by' => $requester->id,
            'project_name' => self::PROJECT_NAME,
            'developer_name' => 'شركة التطوير الحصري التجريبية',
            'developer_contact' => '+966501000777',
            'project_description' => 'سيناريو سيدر: طلب حصري → موافقة → إكمال عقد → رفع CSV للوحدات بنفس منطق الإنتاج.',
            'estimated_units' => 16,
            'location_city' => 'الرياض',
            'location_district' => 'حي الملقا',
            'status' => 'pending',
        ]);

        // موافقة وإكمال عقد بنفس بيانات ExclusiveProjectService::approveRequest / completeContract
        // دون استدعاء الخدمة (تتجنب إشعارات تعتمد أعمدة جداول قد تختلف محلياً).
        $request->approve($pmManager);

        DB::transaction(function () use ($request) {
            $city = City::firstOrCreate(
                ['name' => $request->location_city],
                ['code' => 'EXC']
            );
            $district = District::firstOrCreate(
                [
                    'city_id' => $city->id,
                    'name' => $request->location_district,
                ],
                []
            );

            $contractRow = [
                'user_id' => $request->requested_by,
                'project_name' => $request->project_name,
                'developer_name' => $request->developer_name,
                'developer_number' => $request->developer_contact,
                'city_id' => $city->id,
                'district_id' => $district->id,
                'contract_type' => 'exclusive',
                'units' => [
                    ['type' => 'Apartment', 'count' => 16, 'price' => 800000],
                ],
                'status' => 'pending',
                'notes' => 'إكمال العقد من السيدر (نفس حمولة API لوضع العقد)',
            ];
            ContractCodeGenerator::assignCodeToDataArray($contractRow);

            $contract = Contract::create($contractRow);
            $request->completeContract($contract);
        });

        $request->refresh();
        $contract = Contract::query()->findOrFail($request->contract_id);

        $this->attachStandardProjectStructure($contract, $pmManager);

        $csv = $this->buildUnitsCsvSample();
        $file = UploadedFile::fake()->createWithContent('exclusive_units_seed.csv', $csv);

        Auth::loginUsingId($pmManager->id);
        try {
            /** @var ContractUnitService $unitService */
            $unitService = app(ContractUnitService::class);
            $result = $unitService->uploadCsvByContractId($contract->id, $file);
            $this->command?->info(
                'ExclusiveProjectCsvScenarioSeeder: تم رفع CSV — units_created=' . (int) ($result['units_created'] ?? 0)
                . ' contract_id=' . $contract->id
            );
        } catch (Throwable $e) {
            $this->command?->error('ExclusiveProjectCsvScenarioSeeder: فشل رفع CSV — ' . $e->getMessage());
            throw $e;
        } finally {
            Auth::logout();
        }
    }

    /**
     * يحاكي ما بعد إنشاء العقد من المسار الحصري: معلومات عقد، طرف ثاني، أقسام، وسائط، ربط فريق، حالة مكتملة.
     */
    private function attachStandardProjectStructure(Contract $contract, User $pmManager): void
    {
        if (! $contract->info) {
            ContractInfo::factory()->create(['contract_id' => $contract->id]);
        }

        if (! $contract->secondPartyData) {
            SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        }

        $contract->refresh();

        $realEstateImages = config('unsplash_images.real_estate', []);
        $deptImage = $realEstateImages ? Arr::random($realEstateImages) : 'https://via.placeholder.com/1200x800';

        if (! BoardsDepartment::where('contract_id', $contract->id)->exists()) {
            BoardsDepartment::create([
                'contract_id' => $contract->id,
                'has_ads' => true,
                'processed_by' => $pmManager->id,
                'processed_at' => now()->subDays(3),
            ]);
        }

        if (! PhotographyDepartment::where('contract_id', $contract->id)->exists()) {
            PhotographyDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => $deptImage,
                'video_url' => 'https://example.com/video.mp4',
                'description' => 'تصوير سيدر للمشروع الحصري',
                'processed_by' => $pmManager->id,
                'processed_at' => now()->subDays(2),
            ]);
        }

        if (! MontageDepartment::where('contract_id', $contract->id)->exists()) {
            MontageDepartment::create([
                'contract_id' => $contract->id,
                'image_url' => $deptImage,
                'video_url' => 'https://example.com/montage.mp4',
                'description' => 'مونتاج سيدر',
                'processed_by' => $pmManager->id,
                'processed_at' => now()->subDays(2),
            ]);
        }

        ProjectMedia::create([
            'contract_id' => $contract->id,
            'type' => 'image',
            'url' => $deptImage,
            'department' => 'photography',
        ]);

        $leaderTeamId = User::where('email', 'sales.leader@rakez.com')->value('team_id');
        if ($leaderTeamId) {
            $contract->teams()->syncWithoutDetaching([$leaderTeamId]);
        }

        $contract->update([
            'status' => 'completed',
            'project_image_url' => $deptImage,
            'emergency_contact_number' => '0550000001',
            'security_guard_number' => '0550000002',
            'notes' => trim((string) $contract->notes) . ' | اكتمال سيناريو السيدر (هيكل مشروع كامل).',
            'commission_percent' => 3.5,
            'commission_from' => 'المالك',
        ]);
    }

    /**
     * رؤوس أعمدة متوافقة مع {@see ContractUnitService} (إنجليزي/عربي مدعوم في الخدمة).
     */
    private function buildUnitsCsvSample(): string
    {
        $header = 'unit_type,unit_number,status,price,area,floor,bedrooms,bathrooms,private_area_m2,facade,description_en';
        $rows = [];
        for ($i = 1; $i <= 8; $i++) {
            $price = 700000 + ($i * 5000);
            $rows[] = sprintf(
                'Apartment,A-%02d,available,%s,120.5,%d,3,2,12.0,north,Exclusive seed unit %d',
                $i,
                $price,
                ($i % 10) + 1,
                $i
            );
        }

        return $header . "\n" . implode("\n", $rows);
    }
}
