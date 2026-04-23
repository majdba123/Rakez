<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\Contracts\Pages\ViewContract;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ListExclusiveProjectRequests;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ViewExclusiveProjectRequest;
use App\Filament\Admin\Resources\ProjectMedia\Pages\ViewProjectMedia;
use App\Models\ContractInfo;
use App\Models\Contract;
use App\Models\ExclusiveProjectRequest;
use App\Models\GovernanceAuditLog;
use App\Models\MontageDepartment;
use App\Models\PhotographyDepartment;
use App\Models\ProjectMedia;
use App\Models\SecondPartyData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class ProjectsGovernanceActionsTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Contracts & Projects',
        ]);
    }

    #[Test]
    public function projects_admin_can_approve_and_reject_exclusive_project_requests_from_filament(): void
    {
        $projectsAdmin = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'projects-admin-actions@example.com',
        ]);
        $projectsAdmin->assignRole('projects_admin');

        $requester = $this->createSalesStaff([
            'is_active' => true,
            'email' => 'exclusive-requester@example.com',
        ]);

        $pendingRequest = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
            'project_name' => 'Exclusive Review Project',
        ]);

        $rejectedRequest = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $requester->id,
            'status' => 'pending',
            'project_name' => 'Exclusive Reject Project',
        ]);

        $this->actingAs($projectsAdmin);

        Livewire::test(ListExclusiveProjectRequests::class)
            ->assertCanSeeTableRecords([$pendingRequest, $rejectedRequest])
            ->callTableAction('approveRequest', $pendingRequest->getKey())
            ->assertHasNoTableActionErrors();

        Livewire::test(ListExclusiveProjectRequests::class)
            ->callTableAction('rejectRequest', $rejectedRequest->getKey(), [
                'reason' => 'Filament governance rejection.',
            ])
            ->assertHasNoTableActionErrors();

        $pendingRequest->refresh();
        $rejectedRequest->refresh();

        $this->assertSame('approved', $pendingRequest->status);
        $this->assertSame($projectsAdmin->id, $pendingRequest->approved_by);
        $this->assertNotNull($pendingRequest->approved_at);

        $this->assertSame('rejected', $rejectedRequest->status);
        $this->assertSame('Filament governance rejection.', $rejectedRequest->rejection_reason);
    }

    #[Test]
    public function projects_admin_can_open_contract_exclusive_request_and_project_media_view_pages(): void
    {
        $projectsAdmin = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'projects-admin-views@example.com',
        ]);
        $projectsAdmin->assignRole('projects_admin');

        $contract = Contract::factory()->create(['status' => 'pending']);
        $exclusive = ExclusiveProjectRequest::factory()->create([
            'status' => 'pending',
            'project_name' => 'View Page Test Project',
        ]);
        $media = ProjectMedia::create([
            'contract_id' => $contract->id,
            'type' => 'image',
            'url' => 'https://example.com/assets/sample.jpg',
            'department' => 'photography',
        ]);

        $this->actingAs($projectsAdmin);

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->assertSuccessful()
            ->assertSee('Project Tracker')
            ->assertSee('Readiness');

        Livewire::test(ViewExclusiveProjectRequest::class, ['record' => $exclusive->getKey()])
            ->assertSuccessful();

        Livewire::test(ViewProjectMedia::class, ['record' => $media->getKey()])
            ->assertSuccessful();
    }

    #[Test]
    public function erp_admin_cannot_access_exclusive_project_requests_without_top_authority(): void
    {
        $erpAdmin = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'erp-projects-readonly@example.com',
        ]);
        $erpAdmin->assignRole('erp_admin');

        $this->actingAs($erpAdmin);
        $this->get('/admin/exclusive-project-requests')->assertForbidden();
    }

    #[Test]
    public function manage_teams_action_updates_contract_team_pivot_and_writes_audit_log(): void
    {
        $projectsAdmin = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'projects-admin-manage-teams@example.com',
        ]);
        $projectsAdmin->assignRole('projects_admin');

        $contract = Contract::factory()->create(['status' => 'approved']);
        $teamA = Team::factory()->create();
        $teamB = Team::factory()->create();

        $this->actingAs($projectsAdmin);

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->callAction('manageTeams', data: [
                'team_ids' => [$teamA->id, $teamB->id],
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('contract_team', [
            'contract_id' => $contract->id,
            'team_id' => $teamA->id,
        ]);
        $this->assertDatabaseHas('contract_team', [
            'contract_id' => $contract->id,
            'team_id' => $teamB->id,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.contracts.teams_synced',
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
        ]);
    }

    #[Test]
    public function upload_units_csv_action_fails_cleanly_when_contract_info_is_missing(): void
    {
        $projectsAdmin = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'projects-admin-units-upload@example.com',
        ]);
        $projectsAdmin->assignRole('projects_admin');

        $contract = Contract::factory()->create(['status' => 'approved']);

        $file = UploadedFile::fake()->create('units.csv', 1, 'text/csv');

        $this->actingAs($projectsAdmin);

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->callAction('uploadUnitsCsv', data: [
                'csv_file' => $file,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseCount('contract_units', 0);
        $this->assertDatabaseMissing('governance_audit_logs', [
            'event' => 'governance.contracts.units_uploaded',
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
        ]);
    }

    #[Test]
    public function photography_and_montage_actions_delegate_and_write_audit_logs(): void
    {
        $projectsAdmin = $this->createSuperAdmin([
            'is_active' => true,
            'is_manager' => true,
            'email' => 'projects-admin-media-review@example.com',
        ]);
        $projectsAdmin->assignRole('projects_admin');
        $projectsAdmin->givePermissionTo('projects.media.approve');

        $contract = Contract::factory()->create(['status' => 'approved']);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);

        PhotographyDepartment::create([
            'contract_id' => $contract->id,
            'image_url' => 'https://example.com/photo.jpg',
            'video_url' => 'https://example.com/photo.mp4',
            'status' => 'pending',
        ]);
        MontageDepartment::create([
            'contract_id' => $contract->id,
            'image_url' => 'https://example.com/montage.jpg',
            'video_url' => 'https://example.com/montage.mp4',
            'status' => 'pending',
        ]);

        $this->actingAs($projectsAdmin);

        Livewire::test(ViewContract::class, ['record' => $contract->getKey()])
            ->callAction('approvePhotography', data: ['comment' => 'Approved by governance manager'])
            ->assertHasNoActionErrors()
            ->callAction('rejectMontage', data: ['comment' => 'Need edits'])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('photography_departments', [
            'contract_id' => $contract->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('montage_departments', [
            'contract_id' => $contract->id,
            'status' => 'rejected',
            'rejection_comment' => 'Need edits',
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.contracts.photography_approved',
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.contracts.montage_rejected',
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
        ]);
    }

    #[Test]
    public function project_management_pdf_routes_allow_authorized_and_forbid_unauthorized_users(): void
    {
        Storage::disk('local')->put('tests/exclusive-contract.pdf', 'pdf');

        $authorized = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'projects-admin-pdf-auth@example.com',
        ]);
        $authorized->assignRole('projects_admin');
        $authorized->givePermissionTo('exclusive_projects.view');

        $unauthorized = User::factory()->create([
            'is_active' => true,
            'email' => 'projects-admin-pdf-denied@example.com',
            'type' => 'default',
        ]);

        $contract = Contract::factory()->create(['status' => 'approved', 'user_id' => $authorized->id]);
        ContractInfo::factory()->create(['contract_id' => $contract->id]);
        SecondPartyData::create([
            'contract_id' => $contract->id,
            'real_estate_papers_url' => 'https://example.com/real-estate.pdf',
            'plans_equipment_docs_url' => 'https://example.com/plans.pdf',
            'project_logo_url' => 'https://example.com/logo.png',
            'prices_units_url' => 'https://example.com/prices.pdf',
            'marketing_license_url' => 'https://example.com/license.pdf',
            'advertiser_section_url' => '123456',
            'processed_by' => $authorized->id,
            'processed_at' => now(),
        ]);

        $exclusive = ExclusiveProjectRequest::factory()->create([
            'requested_by' => $authorized->id,
            'status' => 'contract_completed',
            'contract_pdf_path' => 'tests/exclusive-contract.pdf',
        ]);

        $this->actingAs($authorized)
            ->get(route('filament.pm.contracts.contract_info_pdf', ['contractId' => $contract->id]))
            ->assertOk();

        $this->actingAs($authorized)
            ->get(route('filament.pm.contracts.second_party_pdf', ['contractId' => $contract->id]))
            ->assertOk();

        $this->actingAs($authorized)
            ->get(route('filament.pm.exclusive.contract_pdf', ['requestId' => $exclusive->id]))
            ->assertOk();

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.projects.pdf.downloaded',
            'subject_type' => Contract::class,
            'subject_id' => $contract->id,
            'actor_id' => $authorized->id,
        ]);
        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.projects.pdf.downloaded',
            'subject_type' => ExclusiveProjectRequest::class,
            'subject_id' => $exclusive->id,
            'actor_id' => $authorized->id,
        ]);
        $this->assertGreaterThanOrEqual(
            3,
            GovernanceAuditLog::query()
                ->where('event', 'governance.projects.pdf.downloaded')
                ->where('actor_id', $authorized->id)
                ->count(),
        );

        $this->actingAs($unauthorized)
            ->get(route('filament.pm.contracts.contract_info_pdf', ['contractId' => $contract->id]))
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->get(route('filament.pm.contracts.second_party_pdf', ['contractId' => $contract->id]))
            ->assertForbidden();

        $this->actingAs($unauthorized)
            ->get(route('filament.pm.exclusive.contract_pdf', ['requestId' => $exclusive->id]))
            ->assertForbidden();
    }
}
