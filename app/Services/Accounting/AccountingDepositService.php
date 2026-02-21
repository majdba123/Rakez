<?php

namespace App\Services\Accounting;

use App\Constants\DepositStatus;
use App\Constants\ReservationStatus;
use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\UserNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingDepositService
{
    /**
     * Get pending deposits needing confirmation.
     */
    public function getPendingDeposits(array $filters = [])
    {
        $query = Deposit::with([
            'salesReservation.contract',
            'salesReservation.contractUnit',
            'salesReservation.commission',
            'salesReservation.marketingEmployee',
            'contract',
            'contractUnit',
        ])
            ->where('status', DepositStatus::PENDING);

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('contract_id', $filters['project_id']);
        }

        if (isset($filters['from_date'])) {
            $query->whereDate('payment_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->whereDate('payment_date', '<=', $filters['to_date']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['commission_source'])) {
            $query->where('commission_source', $filters['commission_source']);
        }

        $paginator = $query->orderBy('payment_date', 'desc')->paginate($filters['per_page'] ?? 15);
        $paginator->setCollection($paginator->getCollection()->map(fn ($deposit) => $this->transformDepositForList($deposit)));

        return $paginator;
    }

    /**
     * Transform a Deposit into a flat list item for deposit management table.
     * Ensures project_name, unit_type, unit_price, final_selling_price, commission_percentage at top level.
     */
    public function transformDepositForList(Deposit $deposit): array
    {
        $contract = $deposit->contract;
        $contractUnit = $deposit->contractUnit;
        $reservation = $deposit->salesReservation;
        $commission = $reservation?->commission;

        $unitPrice = $contractUnit?->price !== null ? round((float) $contractUnit->price, 2) : null;
        $finalSellingPrice = $commission?->final_selling_price
            ?? $reservation?->proposed_price
            ?? $contractUnit?->price
            ?? 0;
        $finalSellingPrice = round((float) $finalSellingPrice, 2);
        $commissionPercentage = $commission !== null && $commission->commission_percentage !== null
            ? round((float) $commission->commission_percentage, 2)
            : null;

        return [
            'id' => $deposit->id,
            'project_name' => $contract?->project_name ?? null,
            'unit_type' => $contractUnit?->unit_type ?? null,
            'unit_price' => $unitPrice,
            'final_selling_price' => $finalSellingPrice,
            'amount' => $deposit->amount !== null ? round((float) $deposit->amount, 2) : null,
            'payment_method' => $deposit->payment_method,
            'client_name' => $deposit->client_name ?? null,
            'payment_date' => $deposit->payment_date?->format('Y-m-d'),
            'commission_source' => $deposit->commission_source,
            'commission_percentage' => $commissionPercentage,
            'status' => $deposit->status,
            'sales_reservation_id' => $deposit->sales_reservation_id,
            'contract_id' => $deposit->contract_id,
            'contract_unit_id' => $deposit->contract_unit_id,
            'contract' => $contract ? ['id' => $contract->id, 'project_name' => $contract->project_name] : null,
            'contract_unit' => $contractUnit ? ['id' => $contractUnit->id, 'unit_number' => $contractUnit->unit_number, 'unit_type' => $contractUnit->unit_type, 'price' => $unitPrice] : null,
        ];
    }

    /**
     * Confirm deposit receipt.
     */
    public function confirmDepositReceipt(int $depositId, int $accountingUserId): Deposit
    {
        $deposit = Deposit::with(['salesReservation.marketingEmployee'])->findOrFail($depositId);

        if ($deposit->status !== DepositStatus::PENDING) {
            throw new Exception('Only pending deposits can be confirmed.');
        }

        DB::beginTransaction();
        try {
            $deposit->confirmReceipt($accountingUserId);

            // Notify marketing employee
            if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
                UserNotification::create([
                    'user_id' => $deposit->salesReservation->marketing_employee_id,
                    'message' => "تم تأكيد استلام العربون بمبلغ {$deposit->amount} ريال سعودي للحجز رقم {$deposit->sales_reservation_id}.",
                    'status' => 'pending',
                ]);
            }

            // Notify credit department
            $this->notifyCreditDepartment($deposit);

            DB::commit();
            return $deposit->fresh(['confirmedBy', 'salesReservation']);
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get deposits needing follow-up.
     */
    public function getDepositFollowUp(array $filters = [])
    {
        $query = SalesReservation::with([
            'contract',
            'contractUnit',
            'marketingEmployee',
            'commission',
            'deposits',
        ])
            ->where('status', ReservationStatus::CONFIRMED);

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

        $paginator = $query->orderBy('confirmed_at', 'desc')->paginate($filters['per_page'] ?? 15);
        $paginator->setCollection($paginator->getCollection()->map(fn ($reservation) => $this->transformFollowUpForList($reservation)));

        return $paginator;
    }

    /**
     * Transform a SalesReservation for follow-up list (المتابعة): project_name, unit_number, client_name, final_selling_price, commission_percentage.
     */
    public function transformFollowUpForList(SalesReservation $reservation): array
    {
        $contract = $reservation->contract;
        $contractUnit = $reservation->contractUnit;
        $commission = $reservation->commission;

        $finalSellingPrice = $commission?->final_selling_price
            ?? $reservation->proposed_price
            ?? $contractUnit?->price
            ?? 0;
        $finalSellingPrice = round((float) $finalSellingPrice, 2);
        $commissionPercentage = $commission !== null && $commission->commission_percentage !== null
            ? round((float) $commission->commission_percentage, 2)
            : null;

        return [
            'id' => $reservation->id,
            'project_name' => $contract?->project_name ?? null,
            'unit_number' => $contractUnit?->unit_number ?? null,
            'unit_type' => $contractUnit?->unit_type ?? null,
            'client_name' => $reservation->client_name ?? null,
            'final_selling_price' => $finalSellingPrice,
            'commission_percentage' => $commissionPercentage,
            'commission_source' => $commission?->commission_source ?? null,
            'contract_id' => $reservation->contract_id,
            'contract_unit_id' => $reservation->contract_unit_id,
            'deposits' => $reservation->deposits?->map(fn ($d) => [
                'id' => $d->id,
                'amount' => $d->amount !== null ? round((float) $d->amount, 2) : null,
                'status' => $d->status,
            ])->values()->all() ?? [],
        ];
    }

    /**
     * Process deposit refund.
     */
    public function processRefund(int $depositId): Deposit
    {
        $deposit = Deposit::with(['salesReservation'])->findOrFail($depositId);

        // Check if refundable based on commission source
        if (!$deposit->isRefundable()) {
            throw new Exception('This deposit is not refundable. Deposits with buyer as commission source are non-refundable.');
        }

        if ($deposit->isRefunded()) {
            throw new Exception('This deposit has already been refunded.');
        }

        if (!in_array($deposit->status, \App\Constants\DepositStatus::receivedOrConfirmed(), true)) {
            throw new Exception('يجب تأكيد استلام العربون أو تسجيله كمستلم قبل إمكانية الإرجاع.');
        }

        DB::beginTransaction();
        try {
            $deposit->refund();

            // Notify relevant parties
            if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
                UserNotification::create([
                    'user_id' => $deposit->salesReservation->marketing_employee_id,
                    'message' => "تم استرداد العربون بمبلغ {$deposit->amount} ريال سعودي للحجز رقم {$deposit->sales_reservation_id}.",
                    'status' => 'pending',
                ]);
            }

            DB::commit();
            return $deposit->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate claim file for commission.
     */
    public function generateClaimFile(int $reservationId): array
    {
        $reservation = SalesReservation::with([
            'contract',
            'contractUnit',
            'commission.distributions.user',
            'deposits',
        ])->findOrFail($reservationId);

        if (!$reservation->commission) {
            throw new Exception('No commission found for this reservation.');
        }

        $commission = $reservation->commission;
        if ($commission->commission_source !== 'owner') {
            throw new Exception('ملف المطالبة متاح فقط عندما تكون نسبة السعي من المالك.');
        }

        $finalSellingPrice = $commission->final_selling_price ?? $reservation->proposed_price ?? $reservation->contractUnit?->price ?? 0;
        $finalSellingPrice = round((float) $finalSellingPrice, 2);

        // Generate claim file data
        $claimData = [
            'reservation_id' => $reservation->id,
            'project_name' => $reservation->contract?->project_name ?? null,
            'unit_number' => $reservation->contractUnit?->unit_number ?? null,
            'unit_type' => $reservation->contractUnit?->unit_type ?? null,
            'client_name' => $reservation->client_name,
            'final_selling_price' => $finalSellingPrice,
            'commission_percentage' => $commission->commission_percentage !== null ? round((float) $commission->commission_percentage, 2) : null,
            'commission_amount' => $commission->net_amount !== null ? round((float) $commission->net_amount, 2) : null,
            'commission_source' => $commission->commission_source,
            'deposit_amount' => round($reservation->deposits->sum('amount'), 2),
            'distributions' => $commission->distributions?->map(fn ($d) => [
                'type' => $d->type,
                'employee_name' => $d->user?->name,
                'percentage' => $d->percentage !== null ? round((float) $d->percentage, 2) : null,
                'amount' => $d->amount !== null ? round((float) $d->amount, 2) : null,
            ])->values()->all() ?? [],
            'generated_at' => now()->toDateTimeString(),
        ];

        // In a real implementation, you would generate a PDF here
        // For now, we return the data structure
        return $claimData;
    }

    /**
     * Notify credit department about confirmed deposit.
     */
    protected function notifyCreditDepartment(Deposit $deposit): void
    {
        $message = sprintf(
            'تم تأكيد استلام العربون بمبلغ %s ريال سعودي للمشروع: %s - الوحدة: %s',
            $deposit->amount,
            $deposit->contract?->project_name ?? '-',
            $deposit->contractUnit?->unit_number ?? '-'
        );

        $creditUsers = User::where('type', 'credit')->get();
        foreach ($creditUsers as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }
}
