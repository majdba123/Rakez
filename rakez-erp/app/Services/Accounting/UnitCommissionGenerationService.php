<?php

namespace App\Services\Accounting;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\ProjectCommissionSetting;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitCommissionGenerationService
{
    private const DISTRIBUTION_TOTAL_EPSILON = 0.02;

    public function __construct(
        private UnitCommissionPreviewService $unitCommissionPreviewService,
        private CommissionDistributionLegacyTypeMapper $legacyTypeMapper,
    ) {}

    /**
     * @return array<string, mixed> Same shape as Stage 3 unit preview for reuse / consistency checks.
     */
    public function preview(SalesReservation $reservation): array
    {
        return $this->unitCommissionPreviewService->preview($reservation);
    }

    public function generate(SalesReservation $reservation, User $user): Commission
    {
        $preview = $this->unitCommissionPreviewService->preview($reservation);

        $this->assertPreviewSafeForPersist($preview);
        $this->assertReservationAllowsGeneration($reservation);

        $setting = ProjectCommissionSetting::query()
            ->where('project_id', $preview['project_id'])
            ->where('is_active', true)
            ->first();

        if (!$setting instanceof ProjectCommissionSetting) {
            throw ValidationException::withMessages([
                'project_commission_setting' => 'No active project commission setting for this project.',
            ]);
        }

        $unitCommissionAmount = (float) $preview['unit_commission_amount'];

        return DB::transaction(function () use ($reservation, $preview, $setting, $unitCommissionAmount) {

            $commission = Commission::firstOrNew(
                ['sales_reservation_id' => $reservation->id],
                [
                    'contract_unit_id' => $reservation->contract_unit_id,
                ],
            );

            if ($commission->exists && $commission->calculated_by_project_setting) {
                $commission->distributions()->where('status', 'pending')->delete();
            }

            $commission->contract_unit_id = $reservation->contract_unit_id;
            $commission->sales_reservation_id = $reservation->id;
            $commission->final_selling_price = $preview['unit_price'];
            $commission->commission_percentage = (float) $preview['commission_percentage'];
            $commission->commission_source = (string) $preview['commission_source'];
            $commission->project_commission_setting_id = $setting->id;
            $commission->calculation_formula_key = (string) $preview['formula_key'];
            $commission->calculated_by_project_setting = true;
            $commission->marketing_expenses = 0;
            $commission->bank_fees = 0;

            $commission->total_amount = round($unitCommissionAmount, 2);
            $commission->calculateVAT();
            $commission->calculateNetAmount();
            $commission->status = 'pending';

            $commission->save();

            foreach ($preview['distributions'] as $row) {
                $scope = isset($row['source_scope']) ? (string) $row['source_scope'] : '';
                $type = isset($row['source_type']) ? (string) $row['source_type'] : '';
                CommissionDistribution::query()->create([
                    'commission_id' => $commission->id,
                    'user_id' => (int) $row['user_id'],
                    'type' => $this->legacyTypeMapper->map($scope, $type),
                    'external_name' => null,
                    'bank_account' => null,
                    'percentage' => round((float) $row['percentage'], 2),
                    'amount' => round((float) $row['amount'], 2),
                    'source_scope' => $scope !== '' ? $scope : null,
                    'source_type' => $type !== '' ? $type : null,
                    'status' => 'pending',
                    'notes' => null,
                ]);
            }

            $sumPersisted = round((float) $commission->distributions()->sum('amount'), 2);

            if ($sumPersisted > $unitCommissionAmount + self::DISTRIBUTION_TOTAL_EPSILON) {
                throw ValidationException::withMessages([
                    'distributions' => 'Persisted distributions exceed commission net/total.',
                ]);
            }

            return $commission->fresh(['distributions']);
        });
    }

    /** @param  array<string, mixed>  $preview */
    protected function assertPreviewSafeForPersist(array $preview): void
    {
        $amount = (float) ($preview['unit_commission_amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'unit_commission_amount' => 'Unit commission amount must be greater than 0.',
            ]);
        }

        $resolvedTotal = round(array_sum(array_column($preview['distributions'] ?? [], 'amount')), 2);
        if ($resolvedTotal > $amount + self::DISTRIBUTION_TOTAL_EPSILON) {
            throw ValidationException::withMessages([
                'distributions' => 'Resolved distribution total exceeds commission amount.',
            ]);
        }

        $unresolvedTotal = round(array_sum(array_column($preview['unresolved'] ?? [], 'amount')), 2);
        if ($resolvedTotal + $unresolvedTotal > $amount + self::DISTRIBUTION_TOTAL_EPSILON) {
            throw ValidationException::withMessages([
                'allocations' => 'Resolved plus unresolved allocations exceed commission amount.',
            ]);
        }
    }

    protected function assertReservationAllowsGeneration(SalesReservation $reservation): void
    {
        /** @var Commission|null $commission */
        $commission = Commission::query()
            ->where('sales_reservation_id', $reservation->id)
            ->first();

        if (!$commission instanceof Commission) {
            return;
        }

        if ($commission->calculated_by_project_setting !== true) {
            throw ValidationException::withMessages([
                'commission' => 'This reservation has a legacy or manual commission. Project-based generation cannot replace it.',
            ]);
        }

        if (!$commission->isPending()) {
            throw ValidationException::withMessages([
                'commission' => 'Commission is not editable in its current status.',
            ]);
        }

        foreach ($commission->distributions()->get() as $distribution) {
            if ($distribution->status !== 'pending') {
                throw ValidationException::withMessages([
                    'commission' => 'Cannot regenerate: non-pending distributions exist.',
                ]);
            }
        }
    }
}
