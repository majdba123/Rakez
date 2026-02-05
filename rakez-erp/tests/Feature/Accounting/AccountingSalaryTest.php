<?php

namespace Tests\Feature\Accounting;

use Tests\TestCase;
use Tests\Traits\TestsWithPermissions;
use App\Models\User;
use App\Models\AccountingSalaryDistribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class AccountingSalaryTest extends TestCase
{
    use RefreshDatabase, TestsWithPermissions;

    protected User $accountingUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create accounting role with required permissions
        $this->createRoleWithPermissions('accounting', [
            'accounting.salaries.view',
            'accounting.salaries.distribute',
        ]);
        
        $this->accountingUser = User::factory()->create(['type' => 'accounting']);
        $this->accountingUser->assignRole('accounting');
    }

    /** @test */
    public function accounting_user_can_list_employee_salaries()
    {
        Sanctum::actingAs($this->accountingUser);

        User::factory()->create(['salary' => 5000, 'is_active' => true]);

        $response = $this->getJson('/api/accounting/salaries?month=1&year=2026');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'period',
            ]);
    }

    /** @test */
    public function accounting_user_can_view_employee_detail()
    {
        Sanctum::actingAs($this->accountingUser);

        $employee = User::factory()->create(['salary' => 5000]);

        $response = $this->getJson("/api/accounting/salaries/{$employee->id}?month=1&year=2026");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'employee',
                    'period',
                    'sold_units',
                    'summary',
                ],
            ]);
    }

    /** @test */
    public function accounting_user_can_create_salary_distribution()
    {
        Sanctum::actingAs($this->accountingUser);

        $employee = User::factory()->create(['salary' => 5000]);

        $response = $this->postJson("/api/accounting/salaries/{$employee->id}/distribute", [
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('accounting_salary_distributions', [
            'user_id' => $employee->id,
            'month' => 1,
            'year' => 2026,
            'base_salary' => 5000,
        ]);
    }

    /** @test */
    public function cannot_create_duplicate_salary_distribution()
    {
        Sanctum::actingAs($this->accountingUser);

        $employee = User::factory()->create(['salary' => 5000]);
        AccountingSalaryDistribution::factory()->create([
            'user_id' => $employee->id,
            'month' => 1,
            'year' => 2026,
        ]);

        $response = $this->postJson("/api/accounting/salaries/{$employee->id}/distribute", [
            'month' => 1,
            'year' => 2026,
        ]);

        $response->assertStatus(400);
    }

    /** @test */
    public function accounting_user_can_approve_salary_distribution()
    {
        Sanctum::actingAs($this->accountingUser);

        $distribution = AccountingSalaryDistribution::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/accounting/salaries/distributions/{$distribution->id}/approve");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('accounting_salary_distributions', [
            'id' => $distribution->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function accounting_user_can_mark_salary_as_paid()
    {
        Sanctum::actingAs($this->accountingUser);

        $distribution = AccountingSalaryDistribution::factory()->create(['status' => 'approved']);

        $response = $this->postJson("/api/accounting/salaries/distributions/{$distribution->id}/paid");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('accounting_salary_distributions', [
            'id' => $distribution->id,
            'status' => 'paid',
        ]);
    }
}
