<?php

namespace Tests\Feature\Governance;

use App\Filament\Admin\Resources\AccountingClaimFiles\Pages\ListAccountingClaimFiles;
use App\Filament\Admin\Resources\ClaimFiles\Pages\ListClaimFiles;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Auth\BasePermissionTestCase;

class ClaimFileHeaderActionsParityTest extends BasePermissionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('governance.enabled_sections', [
            'Overview',
            'Access Governance',
            'Governance Observability',
            'Credit Oversight',
            'Accounting & Finance',
        ]);
    }

    #[Test]
    public function claim_file_and_accounting_claim_file_pages_share_the_same_generation_header_actions(): void
    {
        $admin = $this->createSuperAdmin([
            'is_active' => true,
            'email' => 'claim-actions-parity-admin@example.com',
        ]);

        $marketer = User::factory()->create([
            'type' => 'sales',
            'is_active' => true,
        ]);
        $marketer->syncRolesFromType();

        $contract = Contract::factory()->create();
        $unit = ContractUnit::factory()->create([
            'contract_id' => $contract->id,
        ]);

        SalesReservation::factory()->create([
            'contract_id' => $contract->id,
            'contract_unit_id' => $unit->id,
            'marketing_employee_id' => $marketer->id,
            'status' => 'confirmed',
            'credit_status' => 'sold',
            'purchase_mechanism' => 'cash',
            'payment_method' => 'cash',
        ]);

        $this->actingAs($admin);

        $claimActionNames = collect(Livewire::test(ListClaimFiles::class)->instance()->getCachedHeaderActions())
            ->map(fn ($action): string => $action->getName())
            ->values()
            ->all();

        $accountingActionNames = collect(Livewire::test(ListAccountingClaimFiles::class)->instance()->getCachedHeaderActions())
            ->map(fn ($action): string => $action->getName())
            ->values()
            ->all();

        $this->assertSame($claimActionNames, $accountingActionNames);
        $this->assertSame(['generateBulk', 'generateCombined'], $claimActionNames);
    }
}

