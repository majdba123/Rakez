<?php

namespace App\Services\Sales;

use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\ContractUnit;
use App\Models\SalesReservation;
use App\Models\User;
use App\Services\Sales\SalesNotificationService;
use App\Exceptions\CommissionException;
use Illuminate\Support\Facades\DB;
use Exception;

class CommissionService
{
    protected SalesNotificationService $notificationService;

    public function __construct(SalesNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a commission for a sold unit.
     */
    public function createCommission(
        int $contractUnitId,
        int $salesReservationId,
        float $finalSellingPrice,
        float $commissionPercentage,
        string $commissionSource,
        ?string $teamResponsible = null
    ): Commission {
        // Check if commission already exists for this unit
        $existingCommission = Commission::where('contract_unit_id', $contractUnitId)->first();
        if ($existingCommission) {
            throw CommissionException::alreadyExists();
        }

        // Validate commission percentage
        if ($commissionPercentage < 0 || $commissionPercentage > 100) {
            throw CommissionException::invalidPercentage();
        }

        // Validate minimum commission amount (example: 100 SAR)
        $totalAmount = ($finalSellingPrice * $commissionPercentage) / 100;
        if ($totalAmount < 100) {
            throw CommissionException::minimumAmountNotMet(100);
        }

        DB::beginTransaction();
        try {
            $commission = new Commission([
                'contract_unit_id' => $contractUnitId,
                'sales_reservation_id' => $salesReservationId,
                'final_selling_price' => $finalSellingPrice,
                'commission_percentage' => $commissionPercentage,
                'commission_source' => $commissionSource,
                'team_responsible' => $teamResponsible,
                'status' => 'pending',
            ]);

            // Calculate amounts
            $commission->calculateTotalAmount();
            $commission->calculateVAT();
            $commission->marketing_expenses = 0;
            $commission->bank_fees = 0;
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
     * Update commission expenses.
     */
    public function updateExpenses(
        Commission $commission,
        float $marketingExpenses,
        float $bankFees
    ): Commission {
        // Check if commission is in pending status
        if ($commission->status !== 'pending') {
            throw CommissionException::cannotModifyApproved();
        }

        // Validate expenses don't exceed total amount
        $totalExpenses = $marketingExpenses + $bankFees;
        if ($totalExpenses > $commission->total_amount) {
            throw CommissionException::expensesExceedAmount();
        }

        DB::beginTransaction();
        try {
            $commission->marketing_expenses = $marketingExpenses;
            $commission->bank_fees = $bankFees;
            $commission->calculateNetAmount();
            $commission->save();

            // Recalculate all distributions
            $this->recalculateDistributions($commission);

            DB::commit();
            return $commission;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Add distribution to a commission.
     */
    public function addDistribution(
        Commission $commission,
        string $type,
        float $percentage,
        ?int $userId = null,
        ?string $externalName = null,
        ?string $bankAccount = null,
        ?string $notes = null
    ): CommissionDistribution {
        $distribution = new CommissionDistribution([
            'commission_id' => $commission->id,
            'user_id' => $userId,
            'type' => $type,
            'external_name' => $externalName,
            'bank_account' => $bankAccount,
            'percentage' => $percentage,
            'notes' => $notes,
            'status' => 'pending',
        ]);

        $distribution->calculateAmount();
        $distribution->save();

        return $distribution;
    }

    /**
     * Distribute commission for lead generation.
     */
    public function distributeLeadGeneration(
        Commission $commission,
        array $marketers
    ): array {
        $distributions = [];

        foreach ($marketers as $marketer) {
            $distribution = $this->addDistribution(
                $commission,
                'lead_generation',
                $marketer['percentage'],
                $marketer['user_id'],
                null,
                $marketer['bank_account'] ?? null
            );

            $distributions[] = $distribution;
        }

        return $distributions;
    }

    /**
     * Distribute commission for persuasion.
     */
    public function distributePersuasion(
        Commission $commission,
        array $employees
    ): array {
        $distributions = [];

        foreach ($employees as $employee) {
            $distribution = $this->addDistribution(
                $commission,
                'persuasion',
                $employee['percentage'],
                $employee['user_id'],
                null,
                $employee['bank_account'] ?? null
            );

            $distributions[] = $distribution;
        }

        return $distributions;
    }

    /**
     * Distribute commission for closing.
     */
    public function distributeClosing(
        Commission $commission,
        array $closers
    ): array {
        $distributions = [];

        foreach ($closers as $closer) {
            $distribution = $this->addDistribution(
                $commission,
                'closing',
                $closer['percentage'],
                $closer['user_id'],
                null,
                $closer['bank_account'] ?? null
            );

            $distributions[] = $distribution;
        }

        return $distributions;
    }

    /**
     * Distribute commission for management.
     */
    public function distributeManagement(
        Commission $commission,
        array $management
    ): array {
        $distributions = [];

        foreach ($management as $manager) {
            $type = $manager['type'] ?? 'other';
            
            $distribution = $this->addDistribution(
                $commission,
                $type,
                $manager['percentage'],
                $manager['user_id'] ?? null,
                $manager['external_name'] ?? null,
                $manager['bank_account'] ?? null
            );

            $distributions[] = $distribution;
        }

        return $distributions;
    }

    /**
     * Approve a commission distribution.
     */
    public function approveDistribution(
        CommissionDistribution $distribution,
        int $approvedBy
    ): CommissionDistribution {
        $distribution->approve($approvedBy);
        
        // Send notification
        $this->notificationService->notifyDistributionApproved($distribution);
        
        return $distribution;
    }

    /**
     * Reject a commission distribution.
     */
    public function rejectDistribution(
        CommissionDistribution $distribution,
        int $approvedBy,
        ?string $notes = null
    ): CommissionDistribution {
        $distribution->reject($approvedBy, $notes);
        
        // Send notification
        $this->notificationService->notifyDistributionRejected($distribution);
        
        return $distribution;
    }

    /**
     * Approve entire commission (all distributions must be approved first).
     */
    public function approveCommission(Commission $commission): Commission
    {
        // Check if all distributions are approved
        $pendingCount = $commission->distributions()->pending()->count();
        
        if ($pendingCount > 0) {
            throw new Exception("Cannot approve commission. {$pendingCount} distributions are still pending.");
        }

        $commission->approve();
        
        // Send notification
        $this->notificationService->notifyCommissionConfirmed($commission);
        
        return $commission;
    }

    /**
     * Mark commission as paid.
     */
    public function markCommissionAsPaid(Commission $commission): Commission
    {
        if (!$commission->isApproved()) {
            throw new Exception("Cannot mark commission as paid. Commission must be approved first.");
        }

        $commission->markAsPaid();

        // Mark all distributions as paid
        $commission->distributions()->approved()->each(function ($distribution) {
            $distribution->markAsPaid();
        });

        // Send notification
        $this->notificationService->notifyCommissionReceived($commission);

        return $commission;
    }

    /**
     * Get commission summary for display.
     */
    public function getCommissionSummary(Commission $commission): array
    {
        $distributions = $commission->distributions;

        return [
            'commission_id' => $commission->id,
            'final_selling_price' => $commission->final_selling_price,
            'commission_percentage' => $commission->commission_percentage,
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
                    'employee_name' => $dist->getDisplayName(),
                    'bank_account' => $dist->bank_account,
                    'percentage' => $dist->percentage,
                    'amount' => $dist->amount,
                    'status' => $dist->status,
                ];
            }),
            'total_distributed_percentage' => $distributions->sum('percentage'),
            'total_distributed_amount' => $distributions->sum('amount'),
        ];
    }

    /**
     * Validate distribution percentages (must equal 100%).
     */
    public function validateDistributionPercentages(array $distributions): bool
    {
        $total = array_sum(array_column($distributions, 'percentage'));
        return abs($total - 100) < 0.01; // Allow for floating point precision
    }

    /**
     * Generate commission claim file (PDF).
     */
    public function generateClaimFile(Commission $commission): string
    {
        $pdfGenerator = new PdfGeneratorService();
        return $pdfGenerator->generateCommissionClaimPdf($commission);
    }

    /**
     * Recalculate all distribution amounts when commission net amount changes.
     */
    public function recalculateDistributions(Commission $commission): void
    {
        DB::transaction(function () use ($commission) {
            foreach ($commission->distributions as $distribution) {
                $distribution->calculateAmount();
                $distribution->save();
            }
        });
    }

    /**
     * Get commission by unit.
     */
    public function getCommissionByUnit(int $contractUnitId): ?Commission
    {
        return Commission::where('contract_unit_id', $contractUnitId)->first();
    }

    /**
     * Get distributions by type for a commission.
     */
    public function getDistributionsByType(Commission $commission, string $type)
    {
        return $commission->distributions()->byType($type)->get();
    }

    /**
     * Delete a distribution (only if pending).
     */
    public function deleteDistribution(CommissionDistribution $distribution): bool
    {
        if (!$distribution->isPending()) {
            throw new Exception("Cannot delete distribution. Only pending distributions can be deleted.");
        }

        return $distribution->delete();
    }

    /**
     * Update distribution percentage and recalculate amount.
     */
    public function updateDistributionPercentage(
        CommissionDistribution $distribution,
        float $newPercentage
    ): CommissionDistribution {
        if (!$distribution->isPending()) {
            throw new Exception("Cannot update distribution. Only pending distributions can be updated.");
        }

        $distribution->percentage = $newPercentage;
        $distribution->calculateAmount();
        $distribution->save();

        return $distribution;
    }
}
