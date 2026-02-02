<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\Deposit;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Sales\DepositService;
use App\Services\Sales\SalesNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class DepositManagementTest extends TestCase
{
    use RefreshDatabase;

    protected DepositService $depositService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the notification service
        $notificationService = Mockery::mock(SalesNotificationService::class);
        $notificationService->shouldReceive('notifyDepositReceived')->andReturn(null);
        $notificationService->shouldReceive('notifyDepositConfirmed')->andReturn(null);
        $notificationService->shouldReceive('notifyDepositRefunded')->andReturn(null);
        
        $this->depositService = new DepositService($notificationService);
    }

    /**
     * Test creating a deposit.
     */
    public function test_creates_deposit(): void
    {
        $contract = Contract::factory()->create();
        $secondPartyData = \App\Models\SecondPartyData::factory()->create(['contract_id' => $contract->id]);
        $unit = ContractUnit::factory()->create(['second_party_data_id' => $secondPartyData->id]);
        $reservation = SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
        ]);

        $deposit = $this->depositService->createDeposit(
            $reservation->id,
            $contract->id,
            $unit->id,
            5000,
            'bank_transfer',
            'John Doe',
            '2026-01-31',
            'owner',
            'Initial deposit'
        );

        $this->assertInstanceOf(Deposit::class, $deposit);
        $this->assertEquals(5000, $deposit->amount);
        $this->assertEquals('bank_transfer', $deposit->payment_method);
        $this->assertEquals('John Doe', $deposit->client_name);
        $this->assertEquals('owner', $deposit->commission_source);
        $this->assertEquals('pending', $deposit->status);
    }

    /**
     * Test confirming deposit receipt.
     */
    public function test_confirms_deposit_receipt(): void
    {
        $deposit = Deposit::factory()->create(['status' => 'pending']);
        $user = User::factory()->create();

        $this->depositService->confirmReceipt($deposit, $user->id);

        $this->assertEquals('confirmed', $deposit->status);
        $this->assertEquals($user->id, $deposit->confirmed_by);
        $this->assertNotNull($deposit->confirmed_at);
    }

    /**
     * Test marking deposit as received.
     */
    public function test_marks_deposit_as_received(): void
    {
        $deposit = Deposit::factory()->create(['status' => 'pending']);

        $this->depositService->markAsReceived($deposit);

        $this->assertEquals('received', $deposit->status);
    }

    /**
     * Test refunding a deposit with owner commission source.
     */
    public function test_refunds_deposit_with_owner_commission_source(): void
    {
        $deposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'owner',
        ]);

        $this->depositService->refundDeposit($deposit);

        $this->assertEquals('refunded', $deposit->status);
        $this->assertNotNull($deposit->refunded_at);
    }

    /**
     * Test cannot refund deposit with buyer commission source.
     */
    public function test_cannot_refund_deposit_with_buyer_commission_source(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن استرداد وديعة من مصدر المشتري');

        $deposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'buyer',
        ]);

        $this->depositService->refundDeposit($deposit);
    }

    /**
     * Test deposit refundability check.
     */
    public function test_checks_deposit_refundability(): void
    {
        $refundableDeposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'owner',
        ]);

        $nonRefundableDeposit = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'buyer',
        ]);

        $this->assertTrue($refundableDeposit->isRefundable());
        $this->assertFalse($nonRefundableDeposit->isRefundable());
    }

    /**
     * Test deposit status checks.
     */
    public function test_deposit_status_checks(): void
    {
        $pendingDeposit = Deposit::factory()->create(['status' => 'pending']);
        $receivedDeposit = Deposit::factory()->create(['status' => 'received']);
        $refundedDeposit = Deposit::factory()->create(['status' => 'refunded']);
        $confirmedDeposit = Deposit::factory()->create(['status' => 'confirmed']);

        $this->assertTrue($pendingDeposit->isPending());
        $this->assertTrue($receivedDeposit->isReceived());
        $this->assertTrue($refundedDeposit->isRefunded());
        $this->assertTrue($confirmedDeposit->isConfirmed());
    }

    /**
     * Test updating deposit information.
     */
    public function test_updates_deposit_information(): void
    {
        $deposit = Deposit::factory()->create([
            'status' => 'pending',
            'amount' => 5000,
        ]);

        $this->depositService->updateDeposit($deposit, [
            'amount' => 6000,
            'notes' => 'Updated amount',
        ]);

        $this->assertEquals(6000, $deposit->amount);
        $this->assertEquals('Updated amount', $deposit->notes);
    }

    /**
     * Test cannot update non-pending deposit.
     */
    public function test_cannot_update_non_pending_deposit(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot update deposit. Only pending deposits can be updated.');

        $deposit = Deposit::factory()->create(['status' => 'confirmed']);

        $this->depositService->updateDeposit($deposit, ['amount' => 6000]);
    }

    /**
     * Test deleting pending deposit.
     */
    public function test_deletes_pending_deposit(): void
    {
        $deposit = Deposit::factory()->create(['status' => 'pending']);

        $result = $this->depositService->deleteDeposit($deposit);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('deposits', ['id' => $deposit->id]);
    }

    /**
     * Test cannot delete non-pending deposit.
     */
    public function test_cannot_delete_non_pending_deposit(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete deposit. Only pending deposits can be deleted.');

        $deposit = Deposit::factory()->create(['status' => 'confirmed']);

        $this->depositService->deleteDeposit($deposit);
    }

    /**
     * Test getting total deposits for reservation.
     */
    public function test_gets_total_deposits_for_reservation(): void
    {
        $reservation = SalesReservation::factory()->create();

        Deposit::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'amount' => 5000,
            'status' => 'received',
        ]);

        Deposit::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'amount' => 3000,
            'status' => 'confirmed',
        ]);

        Deposit::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'amount' => 2000,
            'status' => 'pending', // Should not be counted
        ]);

        $total = $this->depositService->getTotalDepositsForReservation($reservation->id);

        $this->assertEquals(8000, $total);
    }

    /**
     * Test deposit statistics by project.
     */
    public function test_gets_deposit_stats_by_project(): void
    {
        $contract = Contract::factory()->create();

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 5000,
            'status' => 'received',
        ]);

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 3000,
            'status' => 'confirmed',
        ]);

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 2000,
            'status' => 'refunded',
        ]);

        Deposit::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $stats = $this->depositService->getDepositStatsByProject($contract->id);

        $this->assertEquals(8000, $stats['total_received']);
        $this->assertEquals(2000, $stats['total_refunded']);
        $this->assertEquals(1000, $stats['total_pending']);
        $this->assertEquals(6000, $stats['net_deposits']);
        $this->assertEquals(2, $stats['count_received']);
        $this->assertEquals(1, $stats['count_refunded']);
        $this->assertEquals(1, $stats['count_pending']);
    }

    /**
     * Test bulk confirm deposits.
     */
    public function test_bulk_confirms_deposits(): void
    {
        $user = User::factory()->create();
        $deposit1 = Deposit::factory()->create(['status' => 'pending']);
        $deposit2 = Deposit::factory()->create(['status' => 'received']);
        $deposit3 = Deposit::factory()->create(['status' => 'confirmed']); // Should fail

        $result = $this->depositService->bulkConfirmDeposits(
            [$deposit1->id, $deposit2->id, $deposit3->id],
            $user->id
        );

        $this->assertCount(2, $result['confirmed']);
        $this->assertCount(1, $result['failed']);
        $this->assertContains($deposit1->id, $result['confirmed']);
        $this->assertContains($deposit2->id, $result['confirmed']);
    }

    /**
     * Test can refund check with various scenarios.
     */
    public function test_can_refund_check_with_various_scenarios(): void
    {
        // Refundable: owner source, received status
        $deposit1 = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'owner',
        ]);
        $result1 = $this->depositService->canRefund($deposit1);
        $this->assertTrue($result1['can_refund']);

        // Not refundable: buyer source
        $deposit2 = Deposit::factory()->create([
            'status' => 'received',
            'commission_source' => 'buyer',
        ]);
        $result2 = $this->depositService->canRefund($deposit2);
        $this->assertFalse($result2['can_refund']);
        $this->assertStringContainsString('non-refundable', $result2['reason']);

        // Not refundable: already refunded
        $deposit3 = Deposit::factory()->create([
            'status' => 'refunded',
            'commission_source' => 'owner',
        ]);
        $result3 = $this->depositService->canRefund($deposit3);
        $this->assertFalse($result3['can_refund']);
        $this->assertStringContainsString('already been refunded', $result3['reason']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
