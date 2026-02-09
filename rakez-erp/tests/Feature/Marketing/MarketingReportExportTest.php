<?php

namespace Tests\Feature\Marketing;

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\User;
use App\Models\Contract;
use App\Models\MarketingProject;
use App\Models\EmployeeMarketingPlan;
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
                'Snapchat' => 20,
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
                'Snapchat' => 20,
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
}
