<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\EmployeeWarnings\Pages\ListEmployeeWarnings;
use App\Models\EmployeeWarning;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class HrGovernanceAuditTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'HR Oversight',
        ]);
    }

    #[Test]
    public function hr_team_create_and_warning_delete_emit_governance_audit_events(): void
    {
        $hrAdmin = $this->createGovernanceUser('hr_admin');

        $this->actingAs($hrAdmin);

        Livewire::test(\App\Filament\Admin\Resources\HrTeams\Pages\CreateHrTeam::class)
            ->fillForm([
                'name' => 'Audit HR Team',
                'description' => 'Audit test.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $team = Team::query()->where('name', 'Audit HR Team')->firstOrFail();

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.hr.team.created',
            'actor_id' => $hrAdmin->id,
            'subject_type' => Team::class,
            'subject_id' => $team->id,
        ]);

        $employee = $this->createDefaultUser([
            'is_active' => true,
            'email' => 'audit-warning-employee@example.com',
        ]);

        Livewire::test(ListEmployeeWarnings::class)
            ->callAction('issueWarning', data: [
                'user_id' => $employee->id,
                'type' => 'performance',
                'reason' => 'Audit delete test',
                'details' => 'Details',
            ]);

        $warning = EmployeeWarning::query()->where('reason', 'Audit delete test')->firstOrFail();

        Livewire::test(ListEmployeeWarnings::class)
            ->callTableAction('deleteWarning', $warning->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.hr.warning.deleted',
            'actor_id' => $hrAdmin->id,
            'subject_type' => EmployeeWarning::class,
            'subject_id' => $warning->id,
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
