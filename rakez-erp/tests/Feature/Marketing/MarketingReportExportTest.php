<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
use App\Models\ExpectedBooking;
use App\Models\MarketingBudgetDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MarketingReportExportTest extends TestCase
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
    public function it_exports_employee_plan_as_pdf()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);
        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 10000,
            'marketing_value' => 1000,
            'platform_distribution' => [
                'TikTok' => 20,
                'Meta' => 20,
                'Snap' => 20,
                'YouTube' => 20,
                'LinkedIn' => 10,
                'X' => 10,
            ],
            'campaign_distribution' => [
                'Direct Communication' => 30,
                'Hand Raise' => 30,
                'Impression' => 20,
                'Sales' => 20,
            ],
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->get("/api/marketing/reports/export/{$plan->id}?format=pdf");

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    #[Test]
    public function it_exports_employee_plan_as_excel_csv()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);
        $plan = EmployeeMarketingPlan::create([
            'marketing_project_id' => $project->id,
            'user_id' => $this->marketingUser->id,
            'commission_value' => 10000,
            'marketing_value' => 1000,
            'platform_distribution' => [
                'TikTok' => 20,
                'Meta' => 20,
                'Snap' => 20,
                'YouTube' => 20,
                'LinkedIn' => 10,
                'X' => 10,
            ],
            'campaign_distribution' => [
                'Direct Communication' => 30,
                'Hand Raise' => 30,
                'Impression' => 20,
                'Sales' => 20,
            ],
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->get("/api/marketing/reports/export/{$plan->id}?format=excel");

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    #[Test]
    public function it_returns_expected_bookings_report_with_deposit_value_per_booking()
    {
        $contract = Contract::factory()->create();
        $project = MarketingProject::create(['contract_id' => $contract->id]);

        ExpectedBooking::create([
            'marketing_project_id' => $project->id,
            'direct_communications' => 100,
            'hand_raises' => 100,
            'expected_bookings_count' => 10,
            'expected_booking_value' => 2500000,
            'conversion_rate' => 5,
        ]);

        MarketingBudgetDistribution::create([
            'marketing_project_id' => $project->id,
            'plan_type' => 'employee',
            'total_budget' => 50000,
            'platform_distribution' => ['Meta' => 100],
            'platform_objectives' => ['Meta' => ['impression_percent' => 20, 'lead_percent' => 50, 'direct_contact_percent' => 30]],
            'platform_costs' => ['Meta' => ['cpl' => 25, 'direct_contact_cost' => 35]],
            'conversion_rate' => 5,
            'average_booking_value' => 250000,
        ]);

        $response = $this->actingAs($this->marketingUser, 'sanctum')
            ->getJson('/api/marketing/reports/expected-bookings');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_expected_bookings', 10);

        $data = $response->json('data');
        $this->assertEquals(2500000.0, (float) $data['total_expected_booking_value']);
        $this->assertEquals(50000.0, (float) $data['total_campaign_budget']);
        $this->assertEquals(5000.0, (float) $data['deposit_value_per_booking']);
    }
}
