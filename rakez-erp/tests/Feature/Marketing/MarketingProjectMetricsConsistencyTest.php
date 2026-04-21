<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractInfo;
use App\Models\ContractUnit;
use App\Models\Team;
use App\Models\SalesProjectAssignment;
use App\Models\ProjectMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Marketing Project Metrics Consistency Tests
 *
 * Verify that list and show endpoints return numerically identical metrics
 * for shared fields:
 * - contract_id, project_name, status, commission_percent
 * - units_count.available, units_count.pending
 * - avg_unit_price (from ALL units)
 * - total_available_value (from ONLY available units)
 */
class MarketingProjectMetricsConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $marketingUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->marketingUser = User::factory()->create(['type' => 'marketing']);
        $this->marketingUser->syncRolesFromType();
    }

    #[Test]
    public function marketing_project_show_matches_list_metrics_for_same_contract(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create mixed unit statuses
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 100000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 120000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 110000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 105000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 130000]);

        // Hit list endpoint
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');

        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        // Hit show endpoint
        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        $showData = $showResponse->json('data');

        // Assert shared fields match exactly
        $this->assertEquals($listProject['contract_id'], $showData['contract_id']);
        $this->assertEquals($listProject['project_name'], $showData['project_name']);
        $this->assertEquals($listProject['status'], $showData['status']);
        $this->assertEquals($listProject['commission_percent'], $showData['commission_percent']);
        
        // Assert units_count matches
        $this->assertEquals($listProject['units_count']['available'], $showData['units_count']['available']);
        $this->assertEquals($listProject['units_count']['pending'], $showData['units_count']['pending']);
        
        // Assert numeric metrics match
        $this->assertEquals($listProject['avg_unit_price'], $showData['avg_unit_price']);
        $this->assertEquals($listProject['total_available_value'], $showData['total_available_value']);
    }

    #[Test]
    public function marketing_project_avg_unit_price_uses_all_contract_units_in_both_list_and_show(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create units with mixed statuses
        // Available: 100, 200 (sum=300, count=2)
        // Pending: 150 (sum=150, count=1)
        // Sold: 180 (sum=180, count=1)
        // Total: 5 units, sum=630, avg=630/5=126
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 100]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 200]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 150]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 180]);

        // Hit both endpoints
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        // Verify avg_unit_price is from ALL units
        $expectedAvg = 630 / 4; // (100 + 200 + 150 + 180) / 4 = 132.5
        $this->assertEquals($expectedAvg, $listProject['avg_unit_price']);
        $this->assertEquals($expectedAvg, $showResponse->json('data.avg_unit_price'));
        $this->assertEquals($listProject['avg_unit_price'], $showResponse->json('data.avg_unit_price'));
    }

    #[Test]
    public function marketing_project_total_available_value_uses_only_available_units_in_both_list_and_show(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create mixed units
        // Available: 1000 + 2000 = 3000
        // Pending: 1500 (NOT included)
        // Sold: 2000 (NOT included)
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 1000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 2000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 1500]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold', 'price' => 2000]);

        // Hit both endpoints
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        // Verify total_available_value is ONLY from available units
        $expectedTotal = 3000;
        $this->assertEquals($expectedTotal, $listProject['total_available_value']);
        $this->assertEquals($expectedTotal, $showResponse->json('data.total_available_value'));
        $this->assertEquals($listProject['total_available_value'], $showResponse->json('data.total_available_value'));
    }

    #[Test]
    public function marketing_project_units_count_matches_between_list_and_show(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create: 3 available, 2 pending, 1 sold, 1 reserved
        ContractUnit::factory()->count(3)->create(['contract_id' => $contract->id, 'status' => 'available']);
        ContractUnit::factory()->count(2)->create(['contract_id' => $contract->id, 'status' => 'pending']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'sold']);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'reserved']);

        // Hit both endpoints
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        // Verify units counts match
        $this->assertEquals(3, $listProject['units_count']['available']);
        $this->assertEquals(2, $listProject['units_count']['pending']);
        
        $this->assertEquals(3, $showResponse->json('data.units_count.available'));
        $this->assertEquals(2, $showResponse->json('data.units_count.pending'));
        
        $this->assertEquals($listProject['units_count'], $showResponse->json('data.units_count'));
    }

    #[Test]
    public function marketing_project_metrics_are_scoped_to_same_contract_only(): void
    {
        // Create two contracts
        $contract1 = Contract::factory()->create(['status' => 'completed', 'commission_percent' => 2.5]);
        $contract2 = Contract::factory()->create(['status' => 'completed', 'commission_percent' => 3.0]);
        
        ContractInfo::factory()->create(['contract_id' => $contract1->id]);
        ContractInfo::factory()->create(['contract_id' => $contract2->id]);

        // Contract 1: 2 available @ 100 each = 200 total
        ContractUnit::factory()->count(2)->create([
            'contract_id' => $contract1->id,
            'status' => 'available',
            'price' => 100,
        ]);

        // Contract 2: 3 available @ 200 each = 600 total
        ContractUnit::factory()->count(3)->create([
            'contract_id' => $contract2->id,
            'status' => 'available',
            'price' => 200,
        ]);

        // Get details for contract 1
        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract1->id}");

        // Verify metrics are ONLY for contract 1
        $this->assertEquals(2, $showResponse->json('data.units_count.available'));
        $this->assertEquals(200, $showResponse->json('data.total_available_value'));
        // avg = 200 / 2 = 100
        $this->assertEquals(100, $showResponse->json('data.avg_unit_price'));
    }

    #[Test]
    public function marketing_project_metrics_are_not_duplicated_by_related_joins(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create units: 2 available @ 1000 each = 2000 total
        ContractUnit::factory()->count(2)->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'price' => 1000,
        ]);

        // Attach multiple media/teams (these might cause duplicate rows in joins)
        for ($i = 0; $i < 3; $i++) {
            ProjectMedia::create([
                'contract_id' => $contract->id,
                'type' => 'image',
                'url' => "https://example.com/image-{$i}.jpg",
                'department' => 'photography',
            ]);
        }

        $team = Team::factory()->create();
        $contract->teams()->attach($team);

        // Hit both endpoints
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        // Verify metrics are NOT inflated by joins
        $this->assertEquals(2, $listProject['units_count']['available']);
        $this->assertEquals(2000, $listProject['total_available_value']);
        $expectedAvg = 2000 / 2; // = 1000
        $this->assertEquals($expectedAvg, $listProject['avg_unit_price']);

        // Show endpoint should match
        $this->assertEquals(2, $showResponse->json('data.units_count.available'));
        $this->assertEquals(2000, $showResponse->json('data.total_available_value'));
        $this->assertEquals($expectedAvg, $showResponse->json('data.avg_unit_price'));
    }

    #[Test]
    public function marketing_project_shared_numeric_fields_use_same_casting_and_formatting(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 2.5,
        ]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        // Create units with decimal prices
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 100.50]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 200.75]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 150.33]);

        // Hit both endpoints
        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");

        // Verify both return numeric types, not strings
        $this->assertIsNumeric($listProject['avg_unit_price']);
        $this->assertIsNumeric($showResponse->json('data.avg_unit_price'));
        
        $this->assertIsNumeric($listProject['total_available_value']);
        $this->assertIsNumeric($showResponse->json('data.total_available_value'));
        
        $this->assertIsNumeric($listProject['commission_percent']);
        $this->assertIsNumeric($showResponse->json('data.commission_percent'));
        
        // Verify they match exactly
        $this->assertEquals($listProject['avg_unit_price'], $showResponse->json('data.avg_unit_price'));
        $this->assertEquals($listProject['total_available_value'], $showResponse->json('data.total_available_value'));
        $this->assertEquals($listProject['commission_percent'], $showResponse->json('data.commission_percent'));
    }

    #[Test]
    public function marketing_project_list_and_show_share_exact_numeric_values(): void
    {
        $contract = Contract::factory()->create([
            'status' => 'completed',
            'commission_percent' => 3.75,
            'project_name' => 'Test Project دوم',
        ]);
        ContractInfo::factory()->create([
            'contract_id' => $contract->id,
            'avg_property_value' => 825000,
        ]);

        // Create representative units
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 825000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 825000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'available', 'price' => 825000]);
        ContractUnit::factory()->create(['contract_id' => $contract->id, 'status' => 'pending', 'price' => 825000]);

        $listResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/projects');
        $listProject = collect($listResponse->json('data'))->first(fn($p) => $p['contract_id'] === $contract->id);

        $showResponse = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson("/api/marketing/projects/{$contract->id}");
        $showData = $showResponse->json('data');

        // Strict equality check (numeric values with identical types)
        $this->assertEquals($listProject['contract_id'], $showData['contract_id']);
        $this->assertEquals($listProject['project_name'], $showData['project_name']);
        $this->assertEquals($listProject['units_count']['available'], $showData['units_count']['available']);
        $this->assertEquals($listProject['units_count']['pending'], $showData['units_count']['pending']);
        $this->assertEquals($listProject['avg_unit_price'], $showData['avg_unit_price']);
        $this->assertEquals($listProject['total_available_value'], $showData['total_available_value']);
        $this->assertEquals($listProject['commission_percent'], $showData['commission_percent']);
        
        // Verify they are numeric types
        $this->assertIsNumeric($listProject['avg_unit_price']);
        $this->assertIsNumeric($showData['avg_unit_price']);
        $this->assertIsNumeric($listProject['commission_percent']);
        $this->assertIsNumeric($showData['commission_percent']);
    }
}
