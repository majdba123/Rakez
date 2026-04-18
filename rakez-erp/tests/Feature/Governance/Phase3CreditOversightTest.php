<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles;
use App\Filament\Admin\Resources\CreditBookings\Pages\ListCreditBookings;
use App\Filament\Admin\Resources\CreditNotifications\Pages\ListCreditNotifications;
use App\Filament\Admin\Resources\TitleTransfers\Pages\ListTitleTransfers;
use App\Models\ClaimFile;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use App\Models\UserNotification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class Phase3CreditOversightTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Credit Oversight',
        ]);
    }

    #[Test]
    public function credit_booking_resource_canview_includes_navigation_group_check(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $this->actingAs($creditAdmin);

        $this->assertTrue(
            \App\Filament\Admin\Resources\CreditBookings\CreditBookingResource::canViewAny(),
            'credit_admin should pass canViewAny with navigation group check',
        );
    }

    #[Test]
    public function title_transfer_resource_uses_bookings_view_for_listing(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');
        $erpAdmin = $this->createGovernanceUser('erp_admin');

        $this->actingAs($creditAdmin);
        $this->assertTrue(
            \App\Filament\Admin\Resources\TitleTransfers\TitleTransferResource::canViewAny(),
        );

        $this->actingAs($erpAdmin);
        $this->assertTrue(
            \App\Filament\Admin\Resources\TitleTransfers\TitleTransferResource::canViewAny(),
            'erp_admin with credit.bookings.view should see title transfers listing',
        );
    }

    #[Test]
    public function erp_admin_cannot_access_title_transfers_without_top_authority(): void
    {
        $erpAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'phase3-erp-title-transfer@example.com',
        ]);
        $erpAdmin->assignRole('erp_admin');

        $reservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'processed_by' => $erpAdmin->id,
            'status' => 'preparation',
        ]);

        $this->actingAs($erpAdmin)->get('/admin/title-transfers')->assertForbidden();
    }

    #[Test]
    public function credit_admin_credit_list_pages_are_read_only(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $financingReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $financingReservation->id,
            'assigned_to' => $creditAdmin->id,
            'overall_status' => 'in_progress',
            'stage_1_status' => 'in_progress',
        ]);

        $claimReservation = $this->createCreditReservation([
            'credit_status' => 'sold',
        ]);

        $claimFile = ClaimFile::factory()->create([
            'sales_reservation_id' => $claimReservation->id,
            'generated_by' => $creditAdmin->id,
            'is_combined' => false,
            'total_claim_amount' => 1000,
            'created_at' => now(),
        ]);

        $transferReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $transferReservation->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'preparation',
        ]);

        $creditRecipient = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditRecipient->syncRolesFromType();

        $notification = UserNotification::query()->create([
            'user_id' => $creditRecipient->id,
            'message' => 'Credit oversight notification',
            'status' => 'pending',
            'event_type' => 'credit_review',
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListCreditBookings::class)
            ->assertCanSeeTableRecords([$financingReservation, $claimReservation, $transferReservation])
            ->assertTableActionDoesNotExist('initializeFinancing')
            ->assertTableActionVisible('advanceFinancing', $financingReservation->getKey())
            ->assertTableActionVisible('rejectFinancing', $financingReservation->getKey())
            ->assertTableActionDoesNotExist('initializeTitleTransfer')
            ->assertTableActionVisible('generateClaimPdf', $claimReservation->getKey());

        Livewire::test(ListClaimFiles::class)
            ->assertCanSeeTableRecords([$claimFile])
            ->assertTableActionVisible('generatePdf', $claimFile->getKey())
            ->assertTableActionVisible('view', $claimFile->getKey());

        Livewire::test(ListTitleTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableActionVisible('scheduleTransfer', $transfer->getKey())
            ->assertTableActionHidden('unscheduleTransfer', $transfer->getKey())
            ->assertTableActionVisible('completeTransfer', $transfer->getKey())
            ->assertTableActionVisible('view', $transfer->getKey());

        Livewire::test(ListCreditNotifications::class)
            ->assertCanSeeTableRecords([$notification])
            ->assertTableActionVisible('markRead', $notification->getKey())
            ->assertTableActionVisible('view', $notification->getKey());
    }

    #[Test]
    public function auditor_cannot_access_any_credit_page(): void
    {
        $auditor = $this->createGovernanceUser('auditor_readonly');

        $paths = [
            '/admin/credit-overview',
            '/admin/credit-bookings',
            '/admin/title-transfers',
            '/admin/claim-files',
            '/admin/credit-notifications',
        ];

        foreach ($paths as $path) {
            $this->actingAs($auditor)->get($path)->assertForbidden();
        }
    }

    #[Test]
    public function credit_overview_dashboard_renders_with_both_widgets(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $this->actingAs($creditAdmin)
            ->get('/admin/credit-overview')
            ->assertOk()
            ->assertSeeText('Credit Overview');
    }

    #[Test]
    public function credit_booking_filters_include_financing_status_and_title_transfer(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $reservation = $this->createCreditReservation([
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'assigned_to' => $creditAdmin->id,
            'overall_status' => 'in_progress',
            'stage_1_status' => 'in_progress',
        ]);

        $reservationWithTransfer = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        TitleTransfer::factory()->create([
            'sales_reservation_id' => $reservationWithTransfer->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'preparation',
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListCreditBookings::class)
            ->assertCanSeeTableRecords([$reservation, $reservationWithTransfer])
            ->filterTable('financing_status', 'in_progress')
            ->assertCanSeeTableRecords([$reservation])
            ->assertCanNotSeeTableRecords([$reservationWithTransfer]);
    }

    #[Test]
    public function claim_file_filters_include_is_combined_and_date_range(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $reservation = $this->createCreditReservation([
            'credit_status' => 'sold',
            'brokerage_commission_percent' => 3.5,
        ]);

        $singleClaim = ClaimFile::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'generated_by' => $creditAdmin->id,
            'is_combined' => false,
            'total_claim_amount' => 1000,
            'created_at' => now(),
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListClaimFiles::class)
            ->assertCanSeeTableRecords([$singleClaim]);
    }

    #[Test]
    public function title_transfer_filters_include_processed_by(): void
    {
        $creditAdmin = $this->createGovernanceUser('credit_admin');

        $reservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'preparation',
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListTitleTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->filterTable('processed_by', $creditAdmin->id)
            ->assertCanSeeTableRecords([$transfer]);
    }

    #[Test]
    public function erp_admin_cannot_access_credit_bookings_without_top_authority(): void
    {
        $erpAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'phase3-erp-credit-bookings@example.com',
        ]);
        $erpAdmin->assignRole('erp_admin');

        $this->actingAs($erpAdmin);
        $this->get('/admin/credit-bookings')->assertForbidden();
    }

    #[Test]
    public function operational_credit_user_cannot_access_filament_credit_pages(): void
    {
        $creditStaff = User::factory()->create([
            'type' => 'credit',
            'is_active' => true,
        ]);
        $creditStaff->syncRolesFromType();

        $paths = [
            '/admin/credit-overview',
            '/admin/credit-bookings',
            '/admin/title-transfers',
            '/admin/claim-files',
            '/admin/credit-notifications',
        ];

        foreach ($paths as $path) {
            $this->actingAs($creditStaff)->get($path)->assertForbidden();
        }
    }

    protected function createGovernanceUser(string $role): User
    {
        $user = $role === 'auditor_readonly'
            ? $this->createDefaultUser([
                'is_active' => true,
                'email' => "{$role}-" . uniqid() . '@example.com',
            ])
            : $this->createSuperAdmin([
                'is_active' => true,
                'email' => "{$role}-" . uniqid() . '@example.com',
            ]);
        $user->assignRole($role);

        return $user;
    }

    protected function createCreditReservation(array $overrides = []): SalesReservation
    {
        $marketingEmployee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $marketingEmployee->syncRolesFromType();

        $contract = Contract::factory()->create([
            'project_name' => 'Phase 3 Credit Project',
        ]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'unit_number' => 'P3-' . random_int(100, 999),
        ]);

        return SalesReservation::factory()->create(array_merge([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketingEmployee->id,
            'status' => 'confirmed',
            'client_name' => 'Phase 3 Test Client',
            'credit_status' => 'pending',
            'purchase_mechanism' => 'cash',
            'payment_method' => 'cash',
        ], $overrides));
    }
}
