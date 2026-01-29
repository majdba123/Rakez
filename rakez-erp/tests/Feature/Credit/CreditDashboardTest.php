<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class CreditDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $creditUser;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::firstOrCreate(['name' => 'credit.dashboard.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'credit.bookings.view', 'guard_name' => 'web']);

        // Create roles
        $creditRole = Role::firstOrCreate(['name' => 'credit', 'guard_name' => 'web']);
        $creditRole->syncPermissions(['credit.dashboard.view', 'credit.bookings.view']);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(['credit.dashboard.view', 'credit.bookings.view']);

        // Create users
        $this->creditUser = User::factory()->create(['type' => 'credit']);
        $this->creditUser->assignRole('credit');

        $this->adminUser = User::factory()->create(['type' => 'admin']);
        $this->adminUser->assignRole('admin');
    }

    public function test_credit_user_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'kpis' => [
                        'confirmed_bookings_count',
                        'negotiation_bookings_count',
                        'waiting_bookings_count',
                        'requires_review_count',
                        'rejected_with_paid_down_payment_count',
                        'overdue_stages',
                    ],
                    'stage_breakdown',
                ],
            ]);
    }

    public function test_admin_can_access_credit_dashboard(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/credit/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_reflects_accurate_kpis(): void
    {
        // Create test data
        SalesReservation::factory()->count(3)->create([
            'status' => 'confirmed',
            'credit_status' => 'pending',
        ]);

        SalesReservation::factory()->count(2)->create([
            'status' => 'under_negotiation',
            'reservation_type' => 'negotiation',
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.confirmed_bookings_count', 3)
            ->assertJsonPath('data.kpis.negotiation_bookings_count', 2);
    }

    public function test_can_refresh_dashboard_cache(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->postJson('/api/credit/dashboard/refresh');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}

