<?php

namespace App\Services\Sales;

use App\Models\Deposit;
use App\Models\SalesReservation;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Services\Sales\SalesNotificationService;
use App\Exceptions\DepositException;
use Illuminate\Support\Facades\DB;
use Exception;

class DepositService
{
    protected SalesNotificationService $notificationService;

    public function __construct(SalesNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Create a new deposit.
     */
    public function createDeposit(
        int $salesReservationId,
        int $contractId,
        int $contractUnitId,
        float $amount,
        string $paymentMethod,
        string $clientName,
        string $paymentDate,
        string $commissionSource,
        ?string $notes = null
    ): Deposit {
        // Validate amount is positive
        if ($amount <= 0) {
            throw DepositException::negativeAmount();
        }

        // Check if payment date is not in future
        if (strtotime($paymentDate) > time()) {
            throw DepositException::paymentDateInFuture();
        }

        // Validate payment method
        $validMethods = ['bank_transfer', 'cash', 'bank_financing'];
        if (!in_array($paymentMethod, $validMethods)) {
            throw DepositException::invalidPaymentMethod($paymentMethod);
        }

        DB::beginTransaction();
        try {
            $deposit = Deposit::create([
                'sales_reservation_id' => $salesReservationId,
                'contract_id' => $contractId,
                'contract_unit_id' => $contractUnitId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'client_name' => $clientName,
                'payment_date' => $paymentDate,
                'commission_source' => $commissionSource,
                'status' => 'pending',
                'notes' => $notes,
            ]);

            DB::commit();
            return $deposit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Confirm receipt of a deposit.
     */
    public function confirmReceipt(Deposit $deposit, int $confirmedBy): Deposit
    {
        // Validate status transition
        if (!$deposit->isPending() && !$deposit->isReceived()) {
            throw DepositException::invalidStatusTransition(
                $deposit->status,
                'confirmed'
            );
        }

        // Check if already refunded
        if ($deposit->isRefunded()) {
            throw DepositException::alreadyRefunded();
        }

        DB::beginTransaction();
        try {
            $deposit->confirmReceipt($confirmedBy);

            // Send notification
            $this->notificationService->notifyDepositConfirmed($deposit);

            DB::commit();
            return $deposit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark deposit as received.
     */
    public function markAsReceived(Deposit $deposit): Deposit
    {
        // Validate status transition
        if (!$deposit->isPending()) {
            throw DepositException::invalidStatusTransition(
                $deposit->status,
                'received'
            );
        }

        DB::beginTransaction();
        try {
            $deposit->markAsReceived();

            // Send notification
            $this->notificationService->notifyDepositReceived($deposit);

            DB::commit();
            return $deposit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Refund a deposit.
     */
    public function refundDeposit(Deposit $deposit): Deposit
    {
        // Check if deposit is pending
        if ($deposit->isPending()) {
            throw DepositException::cannotRefundPending();
        }

        // Check if already refunded
        if ($deposit->isRefunded()) {
            throw DepositException::alreadyRefunded();
        }

        // Check if buyer source (non-refundable)
        if ($deposit->commission_source === 'buyer') {
            throw DepositException::cannotRefundBuyerSource();
        }

        // Validate status allows refund
        if (!$deposit->isRefundable()) {
            throw DepositException::invalidStatusTransition(
                $deposit->status,
                'refunded'
            );
        }

        DB::beginTransaction();
        try {
            $deposit->refund();

            // Send notification
            $this->notificationService->notifyDepositRefunded($deposit);

            DB::commit();
            return $deposit;
        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get deposits for management view.
     */
    public function getDepositsForManagement(?string $status = null, ?string $from = null, ?string $to = null, int $perPage = 15)
    {
        $query = Deposit::with([
            'salesReservation.marketingEmployee',
            'contract',
            'contractUnit',
            'confirmedBy',
        ]);

        if ($status) {
            $query->where('status', $status);
        }

        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return $query->orderBy('payment_date', 'desc')->paginate($perPage);
    }

    /**
     * Get deposits for follow-up (refund logic).
     */
    public function getDepositsForFollowUp(?string $from = null, ?string $to = null, int $perPage = 15)
    {
        $query = Deposit::with([
            'salesReservation',
            'contract',
            'contractUnit',
        ])
        ->whereIn('status', ['received', 'confirmed']);

        if ($from || $to) {
            $query->dateRange($from, $to);
        }

        return $query->orderBy('payment_date', 'desc')->paginate($perPage);
    }

    /**
     * Generate commission claim file for a deposit.
     */
    public function generateClaimFile(Deposit $deposit): string
    {
        if (!$deposit->isConfirmed()) {
            throw new Exception("Cannot generate claim file. Deposit must be confirmed.");
        }

        $pdfGenerator = new PdfGeneratorService();
        $path = $pdfGenerator->generateDepositClaimPdf($deposit);

        $deposit->claim_file_path = $path;
        $deposit->save();

        return $path;
    }

    /**
     * Get deposit statistics for a project.
     */
    public function getDepositStatsByProject(int $contractId): array
    {
        $deposits = Deposit::where('contract_id', $contractId)->get();

        $received = $deposits->whereIn('status', ['received', 'confirmed']);
        $refunded = $deposits->where('status', 'refunded');
        $pending = $deposits->where('status', 'pending');

        return [
            'total_received' => $received->sum('amount'),
            'total_refunded' => $refunded->sum('amount'),
            'total_pending' => $pending->sum('amount'),
            'net_deposits' => $received->sum('amount') - $refunded->sum('amount'),
            'count_received' => $received->count(),
            'count_refunded' => $refunded->count(),
            'count_pending' => $pending->count(),
            'by_payment_method' => $deposits->groupBy('payment_method')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            }),
            'by_commission_source' => $deposits->groupBy('commission_source')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total' => $group->sum('amount'),
                ];
            }),
        ];
    }

    /**
     * Get deposit details with related information.
     */
    public function getDepositDetails(int $depositId): array
    {
        $deposit = Deposit::with([
            'salesReservation.marketingEmployee',
            'contract',
            'contractUnit',
            'confirmedBy',
        ])->findOrFail($depositId);

        return [
            'deposit' => $deposit,
            'project_name' => $deposit->contract->project_name,
            'unit_number' => $deposit->contractUnit->unit_number,
            'unit_type' => $deposit->contractUnit->unit_type,
            'unit_price' => $deposit->contractUnit->price,
            'client_name' => $deposit->client_name,
            'payment_date' => $deposit->payment_date,
            'payment_method' => $deposit->payment_method,
            'amount' => $deposit->amount,
            'commission_source' => $deposit->commission_source,
            'status' => $deposit->status,
            'is_refundable' => $deposit->isRefundable(),
            'confirmed_by' => $deposit->confirmedBy?->name,
            'confirmed_at' => $deposit->confirmed_at,
        ];
    }

    /**
     * Update deposit information.
     */
    public function updateDeposit(
        Deposit $deposit,
        array $data
    ): Deposit {
        if (!$deposit->isPending()) {
            throw new Exception("Cannot update deposit. Only pending deposits can be updated.");
        }

        $deposit->fill($data);
        $deposit->save();

        return $deposit;
    }

    /**
     * Delete a deposit (only if pending).
     */
    public function deleteDeposit(Deposit $deposit): bool
    {
        if (!$deposit->isPending()) {
            throw new Exception("Cannot delete deposit. Only pending deposits can be deleted.");
        }

        return $deposit->delete();
    }

    /**
     * Get deposits by reservation.
     */
    public function getDepositsByReservation(int $salesReservationId)
    {
        return Deposit::where('sales_reservation_id', $salesReservationId)
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Calculate total deposits for a reservation.
     */
    public function getTotalDepositsForReservation(int $salesReservationId): float
    {
        return (float) Deposit::where('sales_reservation_id', $salesReservationId)
            ->whereIn('status', ['received', 'confirmed'])
            ->sum('amount');
    }

    /**
     * Check if deposit can be refunded based on commission source and status.
     */
    public function canRefund(Deposit $deposit): array
    {
        $canRefund = $deposit->isRefundable();
        $reason = '';

        if ($deposit->isRefunded()) {
            $reason = 'Deposit has already been refunded.';
        } elseif ($deposit->commission_source === 'buyer') {
            $reason = 'Deposits from buyer commission source are non-refundable.';
        } elseif ($deposit->isPending()) {
            $reason = 'Deposit must be received or confirmed before refund.';
        }

        return [
            'can_refund' => $canRefund,
            'reason' => $reason,
        ];
    }

    /**
     * Get refundable deposits for a project.
     */
    public function getRefundableDeposits(int $contractId)
    {
        return Deposit::where('contract_id', $contractId)
            ->where('commission_source', 'owner')
            ->whereIn('status', ['received', 'confirmed'])
            ->with(['salesReservation', 'contractUnit'])
            ->get();
    }

    /**
     * Bulk confirm deposits.
     */
    public function bulkConfirmDeposits(array $depositIds, int $confirmedBy): array
    {
        $confirmed = [];
        $failed = [];

        DB::transaction(function () use ($depositIds, $confirmedBy, &$confirmed, &$failed) {
            foreach ($depositIds as $depositId) {
                try {
                    $deposit = Deposit::findOrFail($depositId);
                    $this->confirmReceipt($deposit, $confirmedBy);
                    $confirmed[] = $depositId;
                } catch (Exception $e) {
                    $failed[] = [
                        'deposit_id' => $depositId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'confirmed' => $confirmed,
            'failed' => $failed,
        ];
    }
}
