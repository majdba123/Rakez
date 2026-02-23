<?php

namespace App\Services\Accounting;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\SalesReservation;
use App\Models\ContractUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Exceptions\CommissionException;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingCommissionService
{
    /**
     * Get list of users eligible for commission distribution (for marketer dropdown).
     * Returns active non-admin users with id and name.
     */
    public function getCommissionEligibleMarketers(): array
    {
        return User::query()
            ->where('is_active', true)
            ->where('type', '!=', 'admin')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /**
     * Get sold units with commission information.
     */
    public function getSoldUnitsWithCommissions(array $filters = [])
    {
        $query = SalesReservation::with([
            'contract',
            'contractUnit',
            'marketingEmployee',
            'commission.distributions.user',
        ])
            ->where('status', 'confirmed');

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('contract_id', $filters['project_id']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('confirmed_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('confirmed_at', '<=', $filters['to_date']);
        }

        if (isset($filters['commission_source'])) {
            $query->whereHas('commission', function ($q) use ($filters) {
                $q->where('commission_source', $filters['commission_source']);
            });
        }

        if (isset($filters['commission_status'])) {
            $query->whereHas('commission', function ($q) use ($filters) {
                $q->where('status', $filters['commission_status']);
            });
        }

        $paginator = $query->orderBy('confirmed_at', 'desc')->paginate($filters['per_page'] ?? 15);
        $paginator->setCollection($paginator->getCollection()->map(fn ($reservation) => $this->transformSoldUnitForList($reservation)));

        return $paginator;
    }

    /**
     * Transform a SalesReservation into a flat list item for the sold-units API response.
     * Ensures project_name, unit_type, final_selling_price, commission fields and team are at top level.
     */
    public function transformSoldUnitForList(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $contractUnit = $reservation->contractUnit;
        $commission = $reservation->commission;

        // Prefer commission price, then negotiated proposed_price, then unit price (cast to float for consistent JSON)
        $finalSellingPrice = $commission?->final_selling_price
            ?? $reservation->proposed_price
            ?? $contractUnit?->price
            ?? 0;
        $finalSellingPrice = round((float) $finalSellingPrice, 2);
        $unitPrice = $contractUnit?->price !== null ? round((float) $contractUnit->price, 2) : null;
        $proposedPrice = $reservation->proposed_price !== null ? round((float) $reservation->proposed_price, 2) : null;

        return [
            'id' => $reservation->id,
            'project_name' => $contract?->project_name ?? null,
            'unit_number' => $contractUnit?->unit_number ?? null,
            'unit_type' => $contractUnit?->unit_type ?? null,
            'final_selling_price' => $finalSellingPrice,
            'proposed_price' => $proposedPrice,
            'unit_price' => $unitPrice,
            'commission_percentage' => $commission !== null ? round((float) $commission->commission_percentage, 2) : null,
            'commission_net' => $commission?->net_amount !== null ? round((float) $commission->net_amount, 2) : null,
            'team_responsible' => $commission?->team_responsible ?? null,
            'team' => $commission?->team_responsible ?? null,
            'commission_status' => $commission?->status ?? null,
            'commission_source' => $commission?->commission_source ?? null,
            'commission_id' => $commission?->id ?? null,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'confirmed_at' => $reservation->confirmed_at?->toDateTimeString(),
            'client_name' => $reservation->client_name ?? null,
            'contract' => $contract ? [
                'id' => $contract->id,
                'project_name' => $contract->project_name,
            ] : null,
            'contract_unit' => $contractUnit ? [
                'id' => $contractUnit->id,
                'unit_number' => $contractUnit->unit_number,
                'unit_type' => $contractUnit->unit_type,
                'price' => $contractUnit->price,
            ] : null,
            'commission' => $commission ? [
                'id' => $commission->id,
                'final_selling_price' => $commission->final_selling_price,
                'commission_percentage' => $commission->commission_percentage,
                'net_amount' => $commission->net_amount,
                'status' => $commission->status,
                'team_responsible' => $commission->team_responsible,
            ] : null,
        ];
    }

    /**
     * Get single sold unit with full commission breakdown.
     */
    public function getSoldUnitWithCommission(int $reservationId)
    {
        return SalesReservation::with([
            'contract',
            'contractUnit',
            'marketingEmployee',
            'commission.distributions.user',
            'commission.distributions.approver',
        ])
            ->where('status', 'confirmed')
            ->findOrFail($reservationId);
    }

    /**
     * Transform a single sold unit for the detail/form page so response matches form data inputs.
     * Returns: unit info, final_selling_price, commission_source, commission_percentage (contract %),
     * summary figures (total_before_tax, vat, marketing_expenses, bank_fees, net_amount),
     * and distributions array (type, type_label, user, percentage, amount, etc.) for مسوقو الجلب / الإقناع / الإقفال / الإدارة.
     */
    public function transformSoldUnitForDetail(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $contractUnit = $reservation->contractUnit;
        $commission = $reservation->commission;

        $finalSellingPrice = $commission?->final_selling_price
            ?? $reservation->proposed_price
            ?? $contractUnit?->price
            ?? 0;
        $finalSellingPrice = round((float) $finalSellingPrice, 2);

        $base = [
            'id' => $reservation->id,
            'project_name' => $contract?->project_name ?? null,
            'unit_number' => $contractUnit?->unit_number ?? null,
            'unit_type' => $contractUnit?->unit_type ?? null,
            'client_name' => $reservation->client_name ?? null,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'final_selling_price' => $finalSellingPrice,
            'proposed_price' => $reservation->proposed_price !== null ? round((float) $reservation->proposed_price, 2) : null,
            'unit_price' => $contractUnit?->price !== null ? round((float) $contractUnit->price, 2) : null,
            'commission_source' => $commission?->commission_source ?? null,
            'commission_percentage' => $commission !== null ? round((float) $commission->commission_percentage, 2) : null,
            'team_responsible' => $commission?->team_responsible ?? null,
            'commission_id' => $commission?->id ?? null,
            'commission_status' => $commission?->status ?? null,
            'distributions' => [],
            'has_external_broker' => false,
        ];

        if (!$commission) {
            return $base;
        }

        $base['total_before_tax'] = $commission->total_amount !== null ? round((float) $commission->total_amount, 2) : null;
        $base['vat'] = $commission->vat !== null ? round((float) $commission->vat, 2) : null;
        $base['marketing_expenses'] = $commission->marketing_expenses !== null ? round((float) $commission->marketing_expenses, 2) : null;
        $base['bank_fees'] = $commission->bank_fees !== null ? round((float) $commission->bank_fees, 2) : null;
        $base['net_amount'] = $commission->net_amount !== null ? round((float) $commission->net_amount, 2) : null;

        $distributions = $commission->distributions ?? [];
        $base['has_external_broker'] = $distributions->contains('type', 'external_marketer');

        $base['distributions'] = $distributions->map(function ($dist) {
            return [
                'id' => $dist->id,
                'type' => $dist->type,
                'type_label' => $this->getDistributionTypeLabel($dist->type),
                'user_id' => $dist->user_id,
                'employee_name' => $dist->user ? $dist->user->name : $dist->external_name,
                'external_name' => $dist->external_name,
                'percentage' => $dist->percentage !== null ? round((float) $dist->percentage, 2) : null,
                'amount' => $dist->amount !== null ? round((float) $dist->amount, 2) : null,
                'bank_account' => $dist->bank_account,
                'status' => $dist->status,
                'notes' => $dist->notes,
            ];
        })->values()->all();

        return $base;
    }

    /**
     * Create manual commission for accounting.
     */
    public function createManualCommission(array $data): Commission
    {
        // Validate commission percentage
        if ($data['commission_percentage'] < 0 || $data['commission_percentage'] > 100) {
            throw new Exception('Commission percentage must be between 0 and 100.');
        }

        DB::beginTransaction();
        try {
            $commission = new Commission([
                'contract_unit_id' => $data['contract_unit_id'],
                'sales_reservation_id' => $data['sales_reservation_id'],
                'final_selling_price' => $data['final_selling_price'],
                'commission_percentage' => $data['commission_percentage'],
                'commission_source' => $data['commission_source'],
                'team_responsible' => $data['team_responsible'] ?? null,
                'status' => 'pending',
            ]);

            // Calculate amounts
            $commission->calculateTotalAmount();
            $commission->calculateVAT();
            $commission->marketing_expenses = $data['marketing_expenses'] ?? 0;
            $commission->bank_fees = $data['bank_fees'] ?? 0;
            $commission->calculateNetAmount();

            $commission->save();

            DB::commit();
            return $commission;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update commission distributions (bulk update).
     */
    public function updateCommissionDistributions(int $commissionId, array $distributions): Commission
    {
        $commission = Commission::findOrFail($commissionId);

        if ($commission->status !== 'pending') {
            throw new Exception('Cannot modify distributions for non-pending commissions.');
        }

        // Validate total percentage equals 100%
        $totalPercentage = array_sum(array_column($distributions, 'percentage'));
        if (abs($totalPercentage - 100) > 0.01) {
            throw new Exception('Total distribution percentage must equal 100%. Current total: ' . $totalPercentage);
        }

        DB::beginTransaction();
        try {
            // Delete existing distributions
            $commission->distributions()->delete();

            // Create new distributions
            foreach ($distributions as $dist) {
                $distribution = new CommissionDistribution([
                    'commission_id' => $commission->id,
                    'user_id' => $dist['user_id'] ?? null,
                    'type' => $dist['type'],
                    'external_name' => $dist['external_name'] ?? null,
                    'bank_account' => $dist['bank_account'] ?? null,
                    'percentage' => $dist['percentage'],
                    'notes' => $dist['notes'] ?? null,
                    'status' => 'pending',
                ]);

                $distribution->calculateAmount();
                $distribution->save();
            }

            DB::commit();
            return $commission->fresh(['distributions.user']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Approve a commission distribution.
     */
    public function approveDistribution(int $distributionId, int $approvedBy): CommissionDistribution
    {
        $distribution = CommissionDistribution::findOrFail($distributionId);

        if ($distribution->status !== 'pending') {
            throw new Exception('Only pending distributions can be approved.');
        }

        DB::beginTransaction();
        try {
            $distribution->approve($approvedBy);

            // Notify employee
            if ($distribution->user_id) {
                UserNotification::create([
                    'user_id' => $distribution->user_id,
                    'message' => "تم الموافقة على توزيع العمولة الخاص بك بمبلغ {$distribution->amount} ريال سعودي.",
                    'status' => 'pending',
                ]);
            }

            DB::commit();
            return $distribution->fresh(['user', 'approver']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Reject a commission distribution.
     */
    public function rejectDistribution(int $distributionId, int $approvedBy, ?string $notes = null): CommissionDistribution
    {
        $distribution = CommissionDistribution::findOrFail($distributionId);

        if ($distribution->status !== 'pending') {
            throw new Exception('Only pending distributions can be rejected.');
        }

        DB::beginTransaction();
        try {
            $distribution->reject($approvedBy, $notes);

            // Notify employee
            if ($distribution->user_id) {
                $message = "تم رفض توزيع العمولة الخاص بك.";
                if ($notes) {
                    $message .= " السبب: {$notes}";
                }
                
                UserNotification::create([
                    'user_id' => $distribution->user_id,
                    'message' => $message,
                    'status' => 'pending',
                ]);
            }

            DB::commit();
            return $distribution->fresh(['user', 'approver']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get commission summary for display (Tab 4).
     */
    public function getCommissionSummary(int $commissionId): array
    {
        $commission = Commission::with(['distributions.user', 'contractUnit', 'salesReservation.contract'])
            ->findOrFail($commissionId);

        $distributions = $commission->distributions;

        return [
            'commission_id' => $commission->id,
            'project_name' => $commission->salesReservation->contract->project_name ?? null,
            'unit_number' => $commission->contractUnit->unit_number ?? null,
            'final_selling_price' => $commission->final_selling_price,
            'commission_percentage' => $commission->commission_percentage,
            'commission_source' => $commission->commission_source,
            'team_responsible' => $commission->team_responsible,
            'total_before_tax' => $commission->total_amount,
            'vat' => $commission->vat,
            'marketing_expenses' => $commission->marketing_expenses,
            'bank_fees' => $commission->bank_fees,
            'net_amount' => $commission->net_amount,
            'status' => $commission->status,
            'distributions' => $distributions->map(function ($dist) {
                return [
                    'id' => $dist->id,
                    'type' => $dist->type,
                    'employee_name' => $dist->user ? $dist->user->name : $dist->external_name,
                    'bank_account' => $dist->bank_account,
                    'percentage' => $dist->percentage,
                    'amount' => $dist->amount,
                    'status' => $dist->status,
                    'approved_at' => $dist->approved_at,
                ];
            }),
            'total_distributed_percentage' => $distributions->sum('percentage'),
            'total_distributed_amount' => $distributions->sum('amount'),
        ];
    }

    /**
     * Confirm commission payment to employee.
     */
    public function confirmCommissionPayment(int $distributionId): CommissionDistribution
    {
        $distribution = CommissionDistribution::with([
            'commission.contractUnit',
            'commission.salesReservation.contract',
        ])->findOrFail($distributionId);

        if ($distribution->status !== 'approved') {
            throw new Exception('Only approved distributions can be marked as paid.');
        }

        DB::beginTransaction();
        try {
            $distribution->markAsPaid();

            // Notify employee per spec 3.4.1: unit number, project, commission type
            if ($distribution->user_id) {
                $commission = $distribution->commission;
                $unitNumber = $commission?->contractUnit?->unit_number ?? '-';
                $projectName = $commission?->salesReservation?->contract?->project_name ?? '-';
                $typeLabel = $this->getDistributionTypeLabel($distribution->type);
                $message = sprintf(
                    'تم تأكيد استحقاقك عمولة على الوحدة رقم (%s)، مشروع (%s)، نوع العمولة (%s)، المبلغ: %s ريال سعودي.',
                    $unitNumber,
                    $projectName,
                    $typeLabel,
                    $distribution->amount
                );
                UserNotification::create([
                    'user_id' => $distribution->user_id,
                    'message' => $message,
                    'status' => 'pending',
                ]);
            }

            DB::commit();
            return $distribution->fresh(['user']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Arabic label for distribution type (for notifications and display).
     */
    protected function getDistributionTypeLabel(string $type): string
    {
        $labels = [
            'lead_generation' => 'جلب',
            'persuasion' => 'إقناع',
            'closing' => 'إقفال',
            'team_leader' => 'قائد فريق',
            'sales_manager' => 'مدير قسم السيلز',
            'project_manager' => 'مدير إدارة المشاريع',
            'external_marketer' => 'مسوق خارجي',
            'other' => 'أخرى',
        ];

        return $labels[$type] ?? $type;
    }
}
