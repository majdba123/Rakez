<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\User;
use App\Services\Sales\CommissionService;
use App\Services\Sales\SalesNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CommissionDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected CommissionService $commissionService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the notification service
        $notificationService = Mockery::mock(SalesNotificationService::class);
        $notificationService->shouldReceive('notifyDistributionApproved')->andReturn(null);
        $notificationService->shouldReceive('notifyDistributionRejected')->andReturn(null);
        $notificationService->shouldReceive('notifyCommissionConfirmed')->andReturn(null);
        $notificationService->shouldReceive('notifyCommissionReceived')->andReturn(null);
        
        $this->commissionService = new CommissionService($notificationService);
    }

    /**
     * Test distribution amount calculation based on percentage.
     */
    public function test_calculates_distribution_amount_correctly(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);

        $distribution = new CommissionDistribution([
            'commission_id' => $commission->id,
            'percentage' => 25,
        ]);
        $distribution->commission()->associate($commission);

        $distribution->calculateAmount();

        $this->assertEquals(5000, $distribution->amount);
    }

    /**
     * Test adding distribution to commission.
     */
    public function test_adds_distribution_to_commission(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $user = User::factory()->create();

        $distribution = $this->commissionService->addDistribution(
            $commission,
            'lead_generation',
            30,
            $user->id,
            null,
            'SA1234567890'
        );

        $this->assertInstanceOf(CommissionDistribution::class, $distribution);
        $this->assertEquals($commission->id, $distribution->commission_id);
        $this->assertEquals($user->id, $distribution->user_id);
        $this->assertEquals('lead_generation', $distribution->type);
        $this->assertEquals(30, $distribution->percentage);
        $this->assertEquals(6000, $distribution->amount);
        $this->assertEquals('SA1234567890', $distribution->bank_account);
    }

    /**
     * Test lead generation distribution.
     */
    public function test_distributes_lead_generation_commission(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $marketers = [
            ['user_id' => $user1->id, 'percentage' => 15, 'bank_account' => 'SA111'],
            ['user_id' => $user2->id, 'percentage' => 10, 'bank_account' => 'SA222'],
        ];

        $distributions = $this->commissionService->distributeLeadGeneration($commission, $marketers);

        $this->assertCount(2, $distributions);
        $this->assertEquals(3000, $distributions[0]->amount); // 15% of 20000
        $this->assertEquals(2000, $distributions[1]->amount); // 10% of 20000
    }

    /**
     * Test persuasion distribution with multiple employees.
     */
    public function test_distributes_persuasion_commission(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $employees = [
            ['user_id' => $user1->id, 'percentage' => 10],
            ['user_id' => $user2->id, 'percentage' => 10],
            ['user_id' => $user3->id, 'percentage' => 5],
        ];

        $distributions = $this->commissionService->distributePersuasion($commission, $employees);

        $this->assertCount(3, $distributions);
        $this->assertEquals('persuasion', $distributions[0]->type);
        $this->assertEquals(2000, $distributions[0]->amount);
        $this->assertEquals(2000, $distributions[1]->amount);
        $this->assertEquals(1000, $distributions[2]->amount);
    }

    /**
     * Test closing distribution.
     */
    public function test_distributes_closing_commission(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $user = User::factory()->create();

        $closers = [
            ['user_id' => $user->id, 'percentage' => 20, 'bank_account' => 'SA333'],
        ];

        $distributions = $this->commissionService->distributeClosing($commission, $closers);

        $this->assertCount(1, $distributions);
        $this->assertEquals('closing', $distributions[0]->type);
        $this->assertEquals(4000, $distributions[0]->amount);
    }

    /**
     * Test management distribution with various types.
     */
    public function test_distributes_management_commission(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $teamLeader = User::factory()->create();
        $salesManager = User::factory()->create();

        $management = [
            ['type' => 'team_leader', 'user_id' => $teamLeader->id, 'percentage' => 10],
            ['type' => 'sales_manager', 'user_id' => $salesManager->id, 'percentage' => 15],
            ['type' => 'external_marketer', 'external_name' => 'John Doe', 'percentage' => 5, 'bank_account' => 'SA444'],
        ];

        $distributions = $this->commissionService->distributeManagement($commission, $management);

        $this->assertCount(3, $distributions);
        $this->assertEquals('team_leader', $distributions[0]->type);
        $this->assertEquals(2000, $distributions[0]->amount);
        $this->assertEquals('sales_manager', $distributions[1]->type);
        $this->assertEquals(3000, $distributions[1]->amount);
        $this->assertEquals('external_marketer', $distributions[2]->type);
        $this->assertEquals('John Doe', $distributions[2]->external_name);
        $this->assertEquals(1000, $distributions[2]->amount);
    }

    /**
     * Test distribution approval.
     */
    public function test_approves_distribution(): void
    {
        $commission = Commission::factory()->create();
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'pending',
        ]);
        $approver = User::factory()->create();

        $this->commissionService->approveDistribution($distribution, $approver->id);

        $this->assertEquals('approved', $distribution->status);
        $this->assertEquals($approver->id, $distribution->approved_by);
        $this->assertNotNull($distribution->approved_at);
    }

    /**
     * Test distribution rejection.
     */
    public function test_rejects_distribution(): void
    {
        $commission = Commission::factory()->create();
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'pending',
        ]);
        $approver = User::factory()->create();

        $this->commissionService->rejectDistribution($distribution, $approver->id, 'Invalid percentage');

        $this->assertEquals('rejected', $distribution->status);
        $this->assertEquals($approver->id, $distribution->approved_by);
        $this->assertEquals('Invalid percentage', $distribution->notes);
        $this->assertNotNull($distribution->approved_at);
    }

    /**
     * Test distribution percentage validation.
     */
    public function test_validates_distribution_percentages(): void
    {
        $distributions = [
            ['percentage' => 30],
            ['percentage' => 25],
            ['percentage' => 20],
            ['percentage' => 15],
            ['percentage' => 10],
        ];

        $isValid = $this->commissionService->validateDistributionPercentages($distributions);

        $this->assertTrue($isValid);
    }

    /**
     * Test distribution percentage validation fails when not 100%.
     */
    public function test_validates_distribution_percentages_fails(): void
    {
        $distributions = [
            ['percentage' => 30],
            ['percentage' => 25],
            ['percentage' => 20],
        ];

        $isValid = $this->commissionService->validateDistributionPercentages($distributions);

        $this->assertFalse($isValid);
    }

    /**
     * Test updating distribution percentage.
     */
    public function test_updates_distribution_percentage(): void
    {
        $commission = Commission::factory()->create([
            'net_amount' => 20000,
        ]);
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'percentage' => 10,
            'amount' => 2000,
            'status' => 'pending',
        ]);

        $this->commissionService->updateDistributionPercentage($distribution, 15);

        $this->assertEquals(15, $distribution->percentage);
        $this->assertEquals(3000, $distribution->amount);
    }

    /**
     * Test cannot update approved distribution.
     */
    public function test_cannot_update_approved_distribution(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot update distribution. Only pending distributions can be updated.');

        $commission = Commission::factory()->create();
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'approved',
        ]);

        $this->commissionService->updateDistributionPercentage($distribution, 20);
    }

    /**
     * Test deleting pending distribution.
     */
    public function test_deletes_pending_distribution(): void
    {
        $commission = Commission::factory()->create();
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'pending',
        ]);

        $result = $this->commissionService->deleteDistribution($distribution);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('commission_distributions', ['id' => $distribution->id]);
    }

    /**
     * Test cannot delete approved distribution.
     */
    public function test_cannot_delete_approved_distribution(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete distribution. Only pending distributions can be deleted.');

        $commission = Commission::factory()->create();
        $distribution = CommissionDistribution::factory()->create([
            'commission_id' => $commission->id,
            'status' => 'approved',
        ]);

        $this->commissionService->deleteDistribution($distribution);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
