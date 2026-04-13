<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\ClaimFiles\ClaimFileResource;
use App\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles;
use App\Filament\Admin\Resources\ClaimFiles\Pages\ViewClaimFile;
use App\Filament\Admin\Resources\CreditBookings\CreditBookingResource;
use App\Filament\Admin\Resources\CreditBookings\Pages\ListCreditBookings;
use App\Filament\Admin\Resources\CreditBookings\Pages\ViewCreditBooking;
use App\Filament\Admin\Resources\TitleTransfers\Pages\ListTitleTransfers;
use App\Filament\Admin\Resources\TitleTransfers\Pages\ViewTitleTransfer;
use App\Filament\Admin\Resources\TitleTransfers\TitleTransferResource;
use App\Models\ClaimFile;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\CreditFinancingTracker;
use App\Models\SalesReservation;
use App\Models\TitleTransfer;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class CreditGovernanceActionsTest extends BasePermissionTestCase
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
    public function credit_admin_can_manage_title_transfer_actions_from_filament_while_other_credit_surfaces_stay_scoped(): void
    {
        $creditAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'credit-admin-readonly@example.com',
        ]);
        $creditAdmin->assignRole('credit_admin');

        $financingReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $financingReservation->id,
            'assigned_to' => $creditAdmin->id,
            'overall_status' => 'in_progress',
            'stage_1_status' => 'in_progress',
            'stage_2_status' => 'pending',
            'stage_3_status' => 'pending',
            'stage_4_status' => 'pending',
            'stage_5_status' => 'pending',
        ]);

        $transferReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $transferReservation->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'scheduled',
            'scheduled_date' => now()->addDay()->toDateString(),
            'notes' => 'Read-only transfer',
        ]);

        $claimReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'sold',
        ]);

        $claimFile = ClaimFile::factory()->create([
            'sales_reservation_id' => $claimReservation->id,
            'generated_by' => $creditAdmin->id,
            'pdf_path' => 'claim_files/existing.pdf',
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListCreditBookings::class)
            ->assertCanSeeTableRecords([$financingReservation, $transferReservation, $claimReservation])
            ->assertTableActionDoesNotExist('initializeFinancing')
            ->assertTableActionVisible('advanceFinancing', $financingReservation->getKey())
            ->assertTableActionVisible('rejectFinancing', $financingReservation->getKey())
            ->assertTableActionDoesNotExist('initializeTitleTransfer')
            ->assertTableActionVisible('generateClaimPdf', $claimReservation->getKey());

        Livewire::test(ListClaimFiles::class)
            ->assertCanSeeTableRecords([$claimFile])
            ->assertTableActionHidden('generatePdf', $claimFile->getKey())
            ->assertTableActionVisible('view', $claimFile->getKey());

        Livewire::test(ListTitleTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableActionVisible('scheduleTransfer', $transfer->getKey())
            ->assertTableActionVisible('unscheduleTransfer', $transfer->getKey())
            ->assertTableActionVisible('completeTransfer', $transfer->getKey())
            ->assertTableActionVisible('view', $transfer->getKey());
    }

    #[Test]
    public function credit_resources_remain_read_only_and_do_not_mutate_domain_state_when_rendered(): void
    {
        $creditAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'credit-admin-state-check@example.com',
        ]);
        $creditAdmin->assignRole('credit_admin');

        $financingReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        $tracker = CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $financingReservation->id,
            'assigned_to' => $creditAdmin->id,
            'overall_status' => 'in_progress',
            'bank_name' => 'Boundary Bank',
            'stage_1_status' => 'completed',
            'stage_2_status' => 'in_progress',
        ]);

        $transferReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $transferReservation->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'scheduled',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Keep unchanged',
        ]);

        $claimReservation = $this->createCreditReservation([
            'purchase_mechanism' => 'cash',
            'credit_status' => 'sold',
        ]);

        $claimFile = ClaimFile::factory()->create([
            'sales_reservation_id' => $claimReservation->id,
            'generated_by' => $creditAdmin->id,
            'pdf_path' => 'claim_files/existing.pdf',
        ]);

        $originalClaimFiles = ClaimFile::count();
        $originalTransfers = TitleTransfer::count();
        $originalTrackers = CreditFinancingTracker::count();
        $originalFinancingStatus = $financingReservation->credit_status;
        $originalTransferStatus = $transfer->status;
        $originalTransferDate = optional($transfer->scheduled_date)?->toDateString();
        $originalTransferNotes = $transfer->notes;
        $originalUnitStatus = $claimReservation->contractUnit->status;
        $originalContractClosed = (bool) $claimReservation->contractUnit->secondPartyData?->contract?->is_closed;
        $originalPdfPath = $claimFile->pdf_path;

        $this->actingAs($creditAdmin)->get('/admin/credit-bookings')->assertOk();
        $this->actingAs($creditAdmin)->get("/admin/credit-bookings/{$financingReservation->id}")->assertOk();
        $this->actingAs($creditAdmin)->get('/admin/title-transfers')->assertOk();
        $this->actingAs($creditAdmin)->get("/admin/title-transfers/{$transfer->id}")->assertOk();
        $this->actingAs($creditAdmin)->get('/admin/claim-files')->assertOk();
        $this->actingAs($creditAdmin)->get("/admin/claim-files/{$claimFile->id}")->assertOk();

        $financingReservation->refresh();
        $transfer->refresh();
        $claimReservation->refresh();
        $claimReservation->contractUnit->refresh();
        $claimFile->refresh();
        $tracker->refresh();

        $this->assertSame($originalTrackers, CreditFinancingTracker::count());
        $this->assertSame($originalTransfers, TitleTransfer::count());
        $this->assertSame($originalClaimFiles, ClaimFile::count());
        $this->assertSame($originalFinancingStatus, $financingReservation->credit_status);
        $this->assertSame('in_progress', $tracker->overall_status);
        $this->assertSame($originalTransferStatus, $transfer->status);
        $this->assertSame($originalTransferDate, optional($transfer->scheduled_date)?->toDateString());
        $this->assertSame($originalTransferNotes, $transfer->notes);
        $this->assertSame($originalUnitStatus, $claimReservation->contractUnit->status);
        $this->assertSame($originalContractClosed, (bool) $claimReservation->contractUnit->secondPartyData?->contract?->is_closed);
        $this->assertSame($originalPdfPath, $claimFile->pdf_path);
    }

    #[Test]
    public function direct_operational_permissions_restore_title_transfer_execution_paths_in_filament(): void
    {
        $user = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'workflow-credit-direct@example.com',
        ]);
        $user->assignRole('workflow_admin');
        $user->givePermissionTo([
            'credit.dashboard.view',
            'credit.bookings.view',
            'credit.financing.manage',
            'credit.title_transfer.manage',
            'credit.claim_files.view',
            'credit.claim_files.manage',
            'credit.claim_files.generate',
        ]);

        $reservation = $this->createCreditReservation([
            'purchase_mechanism' => 'supported_bank',
            'credit_status' => 'in_progress',
        ]);

        CreditFinancingTracker::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'overall_status' => 'in_progress',
        ]);

        $transferReservation = $this->createCreditReservation([
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $transferReservation->id,
            'processed_by' => $user->id,
            'status' => 'preparation',
        ]);

        $claimReservation = $this->createCreditReservation([
            'credit_status' => 'sold',
        ]);

        $claimFile = ClaimFile::factory()->create([
            'sales_reservation_id' => $claimReservation->id,
            'generated_by' => $user->id,
        ]);

        $this->actingAs($user)->get('/admin/credit-overview')->assertOk();
        $this->actingAs($user)->get('/admin/credit-bookings')->assertOk();
        $this->actingAs($user)->get('/admin/title-transfers')->assertOk();
        $this->actingAs($user)->get('/admin/claim-files')->assertOk();

        Livewire::test(ListCreditBookings::class)
            ->assertCanSeeTableRecords([$reservation, $transferReservation, $claimReservation])
            ->assertTableActionDoesNotExist('initializeFinancing')
            ->assertTableActionVisible('advanceFinancing', $reservation->getKey())
            ->assertTableActionVisible('rejectFinancing', $reservation->getKey())
            ->assertTableActionDoesNotExist('initializeTitleTransfer')
            ->assertTableActionVisible('generateClaimPdf', $claimReservation->getKey());

        Livewire::test(ListTitleTransfers::class)
            ->assertCanSeeTableRecords([$transfer])
            ->assertTableActionVisible('scheduleTransfer', $transfer->getKey())
            ->assertTableActionVisible('completeTransfer', $transfer->getKey());

        Livewire::test(ListClaimFiles::class)
            ->assertCanSeeTableRecords([$claimFile])
            ->assertTableActionVisible('generatePdf', $claimFile->getKey());
    }

    #[Test]
    public function credit_admin_can_schedule_unschedule_and_complete_title_transfers_from_filament(): void
    {
        $creditAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'credit-admin-title-transfer-actions@example.com',
        ]);
        $creditAdmin->assignRole('credit_admin');

        $reservation = $this->createCreditReservation([
            'credit_status' => 'title_transfer',
        ]);

        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $reservation->id,
            'processed_by' => $creditAdmin->id,
            'status' => 'preparation',
            'scheduled_date' => null,
        ]);

        $this->actingAs($creditAdmin);

        Livewire::test(ListTitleTransfers::class)
            ->callTableAction('scheduleTransfer', $transfer->getKey(), [
                'scheduled_date' => now()->addDay()->toDateString(),
                'notes' => 'Governance scheduled transfer.',
            ])
            ->assertHasNoTableActionErrors();

        $transfer->refresh();
        $this->assertSame('scheduled', $transfer->status);
        $this->assertSame('Governance scheduled transfer.', $transfer->notes);

        Livewire::test(ListTitleTransfers::class)
            ->callTableAction('unscheduleTransfer', $transfer->getKey())
            ->assertHasNoTableActionErrors();

        $transfer->refresh();
        $this->assertSame('preparation', $transfer->status);
        $this->assertNull($transfer->scheduled_date);

        Livewire::test(ListTitleTransfers::class)
            ->callTableAction('completeTransfer', $transfer->getKey())
            ->assertHasNoTableActionErrors();

        $transfer->refresh();
        $reservation->refresh();
        $reservation->contractUnit->refresh();

        $this->assertSame('completed', $transfer->status);
        $this->assertSame('sold', $reservation->credit_status);
        $this->assertSame('sold', $reservation->contractUnit->status);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.credit.title_transfer.completed',
            'actor_id' => $creditAdmin->id,
            'subject_type' => TitleTransfer::class,
            'subject_id' => $transfer->id,
        ]);
    }

    #[Test]
    public function title_transfer_resource_capabilities_follow_credit_management_permissions(): void
    {
        $user = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'credit-admin-boundary-pages@example.com',
        ]);
        $user->assignRole('credit_admin');

        $reservation = $this->createCreditReservation();
        $transferReservation = $this->createCreditReservation([
            'credit_status' => 'title_transfer',
        ]);
        $transfer = TitleTransfer::factory()->create([
            'sales_reservation_id' => $transferReservation->id,
            'processed_by' => $user->id,
            'status' => 'preparation',
        ]);
        $claimReservation = $this->createCreditReservation([
            'credit_status' => 'sold',
        ]);
        $claimFile = ClaimFile::factory()->create([
            'sales_reservation_id' => $claimReservation->id,
            'generated_by' => $user->id,
        ]);

        $this->actingAs($user);

        $this->assertFalse(CreditBookingResource::canCreate());
        $this->assertTrue(CreditBookingResource::canEdit($reservation));
        $this->assertFalse(CreditBookingResource::canDelete($reservation));
        $this->assertFalse(CreditBookingResource::canDeleteAny());
        $this->assertFalse(CreditBookingResource::canForceDelete($reservation));
        $this->assertFalse(CreditBookingResource::canRestore($reservation));

        $this->assertFalse(TitleTransferResource::canCreate());
        $this->assertFalse(TitleTransferResource::canEdit($transfer));
        $this->assertFalse(TitleTransferResource::canDelete($transfer));
        $this->assertFalse(TitleTransferResource::canDeleteAny());
        $this->assertFalse(TitleTransferResource::canForceDelete($transfer));
        $this->assertFalse(TitleTransferResource::canRestore($transfer));

        $this->assertTrue(ClaimFileResource::canCreate());
        $this->assertFalse(ClaimFileResource::canEdit($claimFile));
        $this->assertFalse(ClaimFileResource::canDelete($claimFile));
        $this->assertFalse(ClaimFileResource::canDeleteAny());
        $this->assertFalse(ClaimFileResource::canForceDelete($claimFile));
        $this->assertFalse(ClaimFileResource::canRestore($claimFile));

        $this->assertSame([], Livewire::test(ListCreditBookings::class)->instance()->getCachedHeaderActions());
        $this->assertSame([], Livewire::test(ViewCreditBooking::class, ['record' => $reservation->getRouteKey()])->instance()->getCachedHeaderActions());
        $this->assertSame([], Livewire::test(ListTitleTransfers::class)->instance()->getCachedHeaderActions());
        $this->assertSame([], Livewire::test(ViewTitleTransfer::class, ['record' => $transfer->getRouteKey()])->instance()->getCachedHeaderActions());
        $this->assertNotEmpty(Livewire::test(ListClaimFiles::class)->instance()->getCachedHeaderActions());
        $this->assertSame([], Livewire::test(ViewClaimFile::class, ['record' => $claimFile->getRouteKey()])->instance()->getCachedHeaderActions());

        $this->assertCount(0, Livewire::test(ListCreditBookings::class)->instance()->getTable()->getFlatBulkActions());
        $this->assertCount(0, Livewire::test(ListTitleTransfers::class)->instance()->getTable()->getFlatBulkActions());
        $this->assertCount(0, Livewire::test(ListClaimFiles::class)->instance()->getTable()->getFlatBulkActions());
    }

    protected function createCreditReservation(array $overrides = []): SalesReservation
    {
        $marketingEmployee = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $marketingEmployee->syncRolesFromType();

        $contract = Contract::factory()->create([
            'project_name' => 'Credit Governance Project',
        ]);

        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
            'status' => 'available',
            'unit_number' => 'CG-' . random_int(100, 999),
        ]);

        return SalesReservation::factory()->create(array_merge([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketingEmployee->id,
            'status' => 'confirmed',
            'client_name' => 'Credit Governance Client',
            'credit_status' => 'pending',
            'purchase_mechanism' => 'cash',
            'payment_method' => 'cash',
        ], $overrides));
    }
}
