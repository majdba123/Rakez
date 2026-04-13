<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\AccountingDeposits\Pages\ListAccountingDeposits;
use App\Filament\Admin\Resources\AccountingNotifications\Pages\ListAccountingNotifications;
use App\Filament\Admin\Resources\AccountingSoldUnits\Pages\ViewAccountingSoldUnit;
use App\Filament\Admin\Resources\CommissionDistributions\Pages\ListCommissionDistributions;
use App\Filament\Admin\Resources\SalaryDistributions\Pages\ListSalaryDistributions;
use App\Models\AccountingSalaryDistribution;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Models\UserNotification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class AccountingGovernanceActionsTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Accounting & Finance',
        ]);
    }

    #[Test]
    public function accounting_admin_can_confirm_deposit_receipt_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'accounting-actions@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $deposit = Deposit::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListAccountingDeposits::class)
            ->assertCanSeeTableRecords([$deposit])
            ->callTableAction('confirmDeposit', $deposit->getKey())
            ->assertHasNoTableActionErrors();

        $deposit->refresh();

        $this->assertSame('confirmed', $deposit->status);
        $this->assertSame($accountingAdmin->id, $deposit->confirmed_by);
        $this->assertNotNull($deposit->confirmed_at);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.deposit.confirmed',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => Deposit::class,
            'subject_id' => $deposit->id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_approve_and_reject_commission_distributions_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'commission-actions@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $pendingDistribution = CommissionDistribution::factory()->create([
            'status' => 'pending',
        ]);

        $rejectedDistribution = CommissionDistribution::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListCommissionDistributions::class)
            ->assertCanSeeTableRecords([$pendingDistribution, $rejectedDistribution])
            ->callTableAction('approveDistribution', $pendingDistribution->getKey())
            ->assertHasNoTableActionErrors();

        Livewire::test(ListCommissionDistributions::class)
            ->callTableAction('rejectDistribution', $rejectedDistribution->getKey(), [
                'notes' => 'Rejected from governance test.',
            ])
            ->assertHasNoTableActionErrors();

        $pendingDistribution->refresh();
        $rejectedDistribution->refresh();

        $this->assertSame('approved', $pendingDistribution->status);
        $this->assertSame($accountingAdmin->id, $pendingDistribution->approved_by);
        $this->assertNotNull($pendingDistribution->approved_at);

        $this->assertSame('rejected', $rejectedDistribution->status);
        $this->assertSame($accountingAdmin->id, $rejectedDistribution->approved_by);
        $this->assertSame('Rejected from governance test.', $rejectedDistribution->notes);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.commission.approved',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => CommissionDistribution::class,
            'subject_id' => $pendingDistribution->id,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.commission.rejected',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => CommissionDistribution::class,
            'subject_id' => $rejectedDistribution->id,
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $pendingDistribution->user_id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_mark_commission_distribution_paid_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'commission-paid-actions@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $distribution = CommissionDistribution::factory()->approved()->create([
            'paid_at' => null,
        ]);

        $existingNotifications = UserNotification::count();

        $this->actingAs($accountingAdmin);

        Livewire::test(ListCommissionDistributions::class)
            ->assertCanSeeTableRecords([$distribution])
            ->callTableAction('markPaid', $distribution->getKey())
            ->assertHasNoTableActionErrors();

        $distribution->refresh();

        $this->assertSame('paid', $distribution->status);
        $this->assertNotNull($distribution->paid_at);
        $this->assertGreaterThan($existingNotifications, UserNotification::count());
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.commission.marked_paid',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => CommissionDistribution::class,
            'subject_id' => $distribution->id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_approve_and_pay_salary_distributions_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'salary-actions@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $pendingDistribution = AccountingSalaryDistribution::factory()->pending()->create();
        $approvedDistribution = AccountingSalaryDistribution::factory()->approved()->create();

        $this->actingAs($accountingAdmin);

        Livewire::test(ListSalaryDistributions::class)
            ->assertCanSeeTableRecords([$pendingDistribution, $approvedDistribution])
            ->callTableAction('approveSalary', $pendingDistribution->getKey())
            ->assertHasNoTableActionErrors();

        Livewire::test(ListSalaryDistributions::class)
            ->callTableAction('markSalaryPaid', $approvedDistribution->getKey())
            ->assertHasNoTableActionErrors();

        $pendingDistribution->refresh();
        $approvedDistribution->refresh();

        $this->assertSame('approved', $pendingDistribution->status);
        $this->assertSame('paid', $approvedDistribution->status);
        $this->assertNotNull($approvedDistribution->paid_at);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.salary.approved',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => AccountingSalaryDistribution::class,
            'subject_id' => $pendingDistribution->id,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.salary.marked_paid',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => AccountingSalaryDistribution::class,
            'subject_id' => $approvedDistribution->id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_process_owner_deposit_refund_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'accounting-refund@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        $deposit = Deposit::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'commission_source' => 'owner',
            'status' => 'confirmed',
            'confirmed_by' => $accountingAdmin->id,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListAccountingDeposits::class)
            ->assertCanSeeTableRecords([$deposit])
            ->callTableAction('processRefund', $deposit->getKey())
            ->assertHasNoTableActionErrors();

        $deposit->refresh();

        $this->assertSame('refunded', $deposit->status);
        $this->assertNotNull($deposit->refunded_at);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.deposit.refunded',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => Deposit::class,
            'subject_id' => $deposit->id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_mark_accounting_notifications_read_from_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'accounting-notif-read@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $recipient = User::factory()->create([
            'type' => 'accounting',
            'is_active' => true,
        ]);

        $notification = UserNotification::create([
            'user_id' => $recipient->id,
            'message' => 'Accounting inbox test.',
            'status' => 'pending',
            'event_type' => 'deposit_received',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListAccountingNotifications::class)
            ->assertCanSeeTableRecords([$notification])
            ->callTableAction('markRead', $notification->getKey())
            ->assertHasNoTableActionErrors();

        $notification->refresh();
        $this->assertSame('read', $notification->status);
    }

    #[Test]
    public function accounting_sold_unit_view_page_renders_in_filament(): void
    {
        $accountingAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'accounting-sold-view@example.com',
        ]);
        $accountingAdmin->assignRole('accounting_admin');

        $reservation = SalesReservation::factory()->create(['status' => 'confirmed']);
        Commission::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'contract_unit_id' => $reservation->contract_unit_id,
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ViewAccountingSoldUnit::class, ['record' => $reservation->getKey()])
            ->assertOk();
    }

    #[Test]
    public function erp_admin_can_view_finance_sections_but_cannot_execute_financial_actions(): void
    {
        $erpAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'erp-finance-readonly@example.com',
        ]);
        $erpAdmin->assignRole('erp_admin');

        $deposit = Deposit::factory()->create([
            'status' => 'pending',
        ]);
        $commissionDistribution = CommissionDistribution::factory()->create([
            'status' => 'pending',
        ]);
        $salaryDistribution = AccountingSalaryDistribution::factory()->pending()->create();

        $this->actingAs($erpAdmin);

        Livewire::test(ListAccountingDeposits::class)
            ->assertCanSeeTableRecords([$deposit])
            ->assertTableActionHidden('confirmDeposit', $deposit->getKey())
            ->assertTableActionHidden('processRefund', $deposit->getKey());

        Livewire::test(ListCommissionDistributions::class)
            ->assertCanSeeTableRecords([$commissionDistribution])
            ->assertTableActionHidden('approveDistribution', $commissionDistribution->getKey())
            ->assertTableActionHidden('rejectDistribution', $commissionDistribution->getKey())
            ->assertTableActionHidden('markPaid', $commissionDistribution->getKey());

        Livewire::test(ListSalaryDistributions::class)
            ->assertCanSeeTableRecords([$salaryDistribution])
            ->assertTableActionHidden('approveSalary', $salaryDistribution->getKey())
            ->assertTableActionHidden('markSalaryPaid', $salaryDistribution->getKey());
    }
}
