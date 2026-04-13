<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\SalesAttendanceSchedules\Pages\ListSalesAttendanceSchedules;
use App\Filament\Admin\Resources\SalesTargets\Pages\ListSalesTargets;
use App\Models\SalesAttendanceSchedule;
use App\Models\SalesTarget;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class SalesGovernanceActionsTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Sales Oversight',
        ]);
    }

    #[Test]
    public function sales_admin_can_update_target_status_from_governance_filament(): void
    {
        $salesAdmin = $this->createGovernanceUser('sales_admin');

        $target = SalesTarget::factory()->create([
            'status' => 'new',
        ]);

        $this->actingAs($salesAdmin);

        Livewire::test(ListSalesTargets::class)
            ->callTableAction('updateTargetStatus', $target->getKey(), [
                'status' => 'in_progress',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('in_progress', $target->fresh()->status);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.sales.target.status_updated',
            'actor_id' => $salesAdmin->id,
            'subject_type' => SalesTarget::class,
            'subject_id' => $target->id,
        ]);
    }

    #[Test]
    public function sales_admin_can_delete_attendance_schedule_from_governance_filament(): void
    {
        $salesAdmin = $this->createGovernanceUser('sales_admin');

        $schedule = SalesAttendanceSchedule::factory()->create();

        $this->actingAs($salesAdmin);

        Livewire::test(ListSalesAttendanceSchedules::class)
            ->callTableAction('deleteSchedule', $schedule->getKey())
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseMissing('sales_attendance_schedules', [
            'id' => $schedule->id,
        ]);

        $this->assertDatabaseHas('governance_audit_logs', [
            'event' => 'governance.sales.attendance.schedule_deleted',
            'actor_id' => $salesAdmin->id,
            'subject_type' => SalesAttendanceSchedule::class,
            'subject_id' => $schedule->id,
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
