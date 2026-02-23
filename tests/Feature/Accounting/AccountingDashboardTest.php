<?php

namespace Tests\Feature\Accounting;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\Deposit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AccountingDashboardTest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create accounting role with required permission
        $this->createRoleWithPermissions('accounting', [
            'accounting.dashboard.view',
        ]);

        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    #[Test]
    public function accounting_user_can_view_dashboard_metrics()
    {
        Sanctum::actingAs($this->accountingUser);

        $response = $this->getJson('/api/accounting/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'units_sold',
                    'total_received_deposits',
                    'total_refunded_deposits',
                    'total_projects_value',
                    'total_sales_value',
                    'total_commissions',
                    'pending_commissions',
                    'approved_commissions',
                ],
            ]);
    }

    #[Test]
    public function dashboard_can_filter_by_date_range()
    {
        Sanctum::actingAs($this->accountingUser);

        $response = $this->getJson('/api/accounting/dashboard?from_date=2026-01-01&to_date=2026-12-31');

        $response->assertStatus(200);
    }

    #[Test]
    public function dashboard_returns_422_with_errors_on_validation_failure()
    {
        Sanctum::actingAs($this->accountingUser);

        $response = $this->getJson('/api/accounting/dashboard?from_date=2026-12-31&to_date=2026-01-01');

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    #[Test]
    public function non_accounting_user_cannot_access_dashboard()
    {
        $salesUser = User::factory()->create(['type' => 'sales']);
        Sanctum::actingAs($salesUser);

        $response = $this->getJson('/api/accounting/dashboard');

        $response->assertStatus(403);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_dashboard()
    {
        $response = $this->getJson('/api/accounting/dashboard');

        $response->assertStatus(401);
    }
}
