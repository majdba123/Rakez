<?php

namespace Tests\Feature\Credit;

use Tests\TestCase;
use App\Models\User;
use App\Models\SalesReservation;
use App\Models\CreditFinancingTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
                        'projects_in_progress_count',
                        'rejected_by_bank_count',
                        'overdue_stages',
                        'pending_accounting_confirmation',
                        'in_title_transfer_count',
                        'sold_projects_count',
                    ],
                    'kpis_labels_ar',
                    'stage_breakdown',
                    'stage_labels_ar',
                    'title_transfer_breakdown' => [
                        'preparation_count',
                        'scheduled_count',
                    ],
                    'title_transfer_labels_ar',
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

    public function test_dashboard_reflects_projects_in_progress_and_rejected_by_bank(): void
    {
        SalesReservation::factory()->count(4)->create([
            'status' => 'confirmed',
            'credit_status' => 'in_progress',
        ]);

        SalesReservation::factory()->count(3)->create([
            'status' => 'confirmed',
            'credit_status' => 'rejected',
        ]);

        SalesReservation::factory()->count(1)->create([
            'status' => 'confirmed',
            'credit_status' => 'rejected',
            'down_payment_confirmed' => true,
        ]);

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.projects_in_progress_count', 4)
            ->assertJsonPath('data.kpis.rejected_by_bank_count', 4)
            ->assertJsonPath('data.kpis.rejected_with_paid_down_payment_count', 1);
    }

    public function test_dashboard_includes_arabic_labels(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis_labels_ar.confirmed_bookings_count', 'الحجوزات المؤكدة')
            ->assertJsonPath('data.kpis_labels_ar.negotiation_bookings_count', 'التفاوض')
            ->assertJsonPath('data.kpis_labels_ar.waiting_bookings_count', 'الانتظار')
            ->assertJsonPath('data.kpis_labels_ar.projects_in_progress_count', 'المشاريع قيد التنفيذ')
            ->assertJsonPath('data.kpis_labels_ar.rejected_by_bank_count', 'المشاريع المرفوضة من البنك')
            ->assertJsonPath('data.kpis_labels_ar.rejected_with_paid_down_payment_count', 'المشاريع التي تم دفع عربون لها وتم رفضها من البنك');
    }

    public function test_dashboard_includes_stage_labels_ar(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.stage_labels_ar.stage_1', 'التواصل مع العميل')
            ->assertJsonPath('data.stage_labels_ar.stage_2', 'رفع الطلب للبنك')
            ->assertJsonPath('data.stage_labels_ar.stage_5', 'الإجراءات البنكية والعقود');
    }

    public function test_dashboard_reflects_requires_review_count_with_overdue_stage(): void
    {
        CreditFinancingTracker::factory()
            ->withOverdueStage(1)
            ->create(['overall_status' => 'in_progress']);

        Cache::forget('credit_dashboard_kpis');

        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.requires_review_count', 1);
    }

    public function test_dashboard_includes_title_transfer_breakdown(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->getJson('/api/credit/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'title_transfer_breakdown' => [
                        'preparation_count',
                        'scheduled_count',
                    ],
                ],
            ])
            ->assertJsonPath('data.title_transfer_labels_ar.preparation_count', 'فترة التجهيز قبل الإفراغ')
            ->assertJsonPath('data.title_transfer_labels_ar.scheduled_count', 'تنفيذ العقود');
    }

    public function test_can_refresh_dashboard_cache(): void
    {
        $response = $this->actingAs($this->creditUser)
            ->postJson('/api/credit/dashboard/refresh');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}

