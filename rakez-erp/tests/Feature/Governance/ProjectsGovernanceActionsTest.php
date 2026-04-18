<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\Contracts\Pages\ViewContract;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ListExclusiveProjectRequests;
use App\Filament\Admin\Resources\ExclusiveProjectRequests\Pages\ViewExclusiveProjectRequest;
use App\Filament\Admin\Resources\ProjectMedia\Pages\ViewProjectMedia;
use App\Models\Contract;
use App\Models\ExclusiveProjectRequest;
use App\Models\ProjectMedia;
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
            ->assertSuccessful();

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
}
