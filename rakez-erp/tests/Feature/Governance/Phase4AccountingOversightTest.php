<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\AccountingClaimFiles\AccountingClaimFileResource;
use App\Filament\Admin\Resources\AccountingDeposits\AccountingDepositResource;
use App\Filament\Admin\Resources\AccountingNotifications\AccountingNotificationResource;
use App\Filament\Admin\Resources\AccountingNotifications\Pages\ListAccountingNotifications;
use App\Filament\Admin\Resources\AccountingSoldUnits\AccountingSoldUnitResource;
use App\Models\Deposit;
use App\Models\User;
use App\Models\UserNotification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class Phase4AccountingOversightTest extends BasePermissionTestCase
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
    public function accounting_notifications_are_visible_only_for_accounting_recipients(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');

        $accountingRecipient = User::factory()->create([
            'type' => 'accounting',
            'is_active' => true,
        ]);
        $creditRecipient = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);

        $accountingNotification = UserNotification::create([
            'user_id' => $accountingRecipient->id,
            'message' => 'Accounting notification for oversight.',
            'status' => 'pending',
            'event_type' => 'deposit_received',
            'context' => ['reservation_id' => 101],
        ]);

        $creditNotification = UserNotification::create([
            'user_id' => $creditRecipient->id,
            'message' => 'Credit notification should stay out of accounting oversight.',
            'status' => 'pending',
            'event_type' => 'deposit_received',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListAccountingNotifications::class)
            ->assertCanSeeTableRecords([$accountingNotification])
            ->assertCanNotSeeTableRecords([$creditNotification]);
    }

    #[Test]
    public function accounting_notifications_page_is_available_to_erp_and_accounting_admin_only(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');
        $erpAdmin = $this->createGovernanceUser('erp_admin');
        $auditor = $this->createGovernanceUser('auditor_readonly');

        $this->actingAs($accountingAdmin)->get('/admin/accounting-notifications')->assertOk();
        $this->actingAs($erpAdmin)->get('/admin/accounting-notifications')->assertOk();
        $this->actingAs($auditor)->get('/admin/accounting-notifications')->assertForbidden();
    }

    #[Test]
    public function accounting_resources_use_group_aware_canview_checks(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');
        $auditor = $this->createGovernanceUser('auditor_readonly');

        $this->actingAs($accountingAdmin);
        $this->assertTrue(AccountingDepositResource::canViewAny());
        $this->assertTrue(AccountingNotificationResource::canViewAny());
        $this->assertTrue(AccountingSoldUnitResource::canViewAny());

        $this->actingAs($auditor);
        $this->assertFalse(AccountingDepositResource::canViewAny());
        $this->assertFalse(AccountingNotificationResource::canViewAny());
        $this->assertFalse(AccountingSoldUnitResource::canViewAny());
    }

    #[Test]
    public function accounting_notifications_resource_is_read_only(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');
        $notification = UserNotification::create([
            'user_id' => User::factory()->create([
                'type' => 'accounting',
                'is_active' => true,
            ])->id,
            'message' => 'Read-only notification.',
            'status' => 'pending',
        ]);

        $this->actingAs($accountingAdmin);

        $this->assertFalse(AccountingNotificationResource::canCreate());
        $this->assertFalse(AccountingNotificationResource::canEdit($notification));
        $this->assertFalse(AccountingNotificationResource::canDelete($notification));
        $this->assertFalse(AccountingNotificationResource::canDeleteAny());
        $this->assertFalse(AccountingNotificationResource::canForceDelete($notification));
        $this->assertFalse(AccountingNotificationResource::canRestore($notification));
    }

    #[Test]
    public function deposit_confirmation_audit_log_contains_governance_event_and_payload(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');
        $deposit = Deposit::factory()->create([
            'status' => 'pending',
            'amount' => 12500,
            'commission_source' => 'owner',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(\App\Filament\Admin\Resources\AccountingDeposits\Pages\ListAccountingDeposits::class)
            ->callTableAction('confirmDeposit', $deposit->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.deposit.confirmed',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => Deposit::class,
            'subject_id' => $deposit->id,
        ]);
    }

    #[Test]
    public function accounting_admin_can_access_accounting_claim_files_under_accounting_finance(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');

        $this->actingAs($accountingAdmin);

        $this->assertTrue(AccountingClaimFileResource::canViewAny());
        $this->get('/admin/accounting-claim-files')->assertOk();
    }

    #[Test]
    public function accounting_notification_mark_read_writes_governance_audit_log(): void
    {
        $accountingAdmin = $this->createGovernanceUser('accounting_admin');

        $recipient = User::factory()->create([
            'type' => 'accounting',
            'is_active' => true,
        ]);

        $notification = UserNotification::create([
            'user_id' => $recipient->id,
            'message' => 'Mark read audit test.',
            'status' => 'pending',
            'event_type' => 'deposit_received',
        ]);

        $this->actingAs($accountingAdmin);

        Livewire::test(ListAccountingNotifications::class)
            ->callTableAction('markRead', $notification->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.accounting.notification.marked_read',
            'actor_id' => $accountingAdmin->id,
            'subject_type' => UserNotification::class,
            'subject_id' => $notification->id,
        ]);
    }

    protected function createGovernanceUser(string $role): User
    {
        $user = $this->createDefaultUser([
            'is_active' => true,
            'email' => "{$role}-" . uniqid() . '@example.com',
        ]);
        $user->assignRole($role);

        return $user;
    }
}
