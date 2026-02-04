<?php

namespace App\Services\Sales;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Models\SalesReservation;
use App\Models\Deposit;
use App\Models\Commission;
use App\Models\CommissionDistribution;
use App\Models\User;
use App\Services\Accounting\AccountingNotificationService;

class SalesNotificationService
{
    protected AccountingNotificationService $accountingNotificationService;

    public function __construct(AccountingNotificationService $accountingNotificationService)
    {
        $this->accountingNotificationService = $accountingNotificationService;
    }
    /**
     * Notify when a unit is reserved.
     */
    public function notifyUnitReserved(SalesReservation $reservation): void
    {
        $message = "Unit {$reservation->contractUnit->unit_number} in project {$reservation->contract->project_name} has been reserved by {$reservation->client_name}.";

        // Notify the marketing employee
        UserNotification::create([
            'user_id' => $reservation->marketing_employee_id,
            'message' => $message,
            'status' => 'pending',
        ]);

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyUnitReserved($reservation);
    }

    /**
     * Notify when a deposit is received.
     */
    public function notifyDepositReceived(Deposit $deposit): void
    {
        $message = "Deposit of {$deposit->amount} SAR received for unit {$deposit->contractUnit->unit_number} in project {$deposit->contract->project_name}.";

        // Notify the marketing employee who made the reservation
        if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $deposit->salesReservation->marketing_employee_id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyDepositReceived($deposit);
    }

    /**
     * Notify when a unit is vacated.
     */
    public function notifyUnitVacated(SalesReservation $reservation): void
    {
        $message = "Unit {$reservation->contractUnit->unit_number} in project {$reservation->contract->project_name} has been vacated.";

        // Notify the marketing employee
        UserNotification::create([
            'user_id' => $reservation->marketing_employee_id,
            'message' => $message,
            'status' => 'pending',
        ]);

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyUnitVacated($reservation);
    }

    /**
     * Notify when a reservation is canceled.
     */
    public function notifyReservationCanceled(SalesReservation $reservation): void
    {
        $message = "Reservation for unit {$reservation->contractUnit->unit_number} in project {$reservation->contract->project_name} has been canceled.";

        // Notify the marketing employee
        UserNotification::create([
            'user_id' => $reservation->marketing_employee_id,
            'message' => $message,
            'status' => 'pending',
        ]);

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyReservationCancelled($reservation);
    }

    /**
     * Notify when a commission is confirmed/approved.
     */
    public function notifyCommissionConfirmed(Commission $commission): void
    {
        $message = "Commission for unit {$commission->contractUnit->unit_number} has been confirmed. Total amount: {$commission->net_amount} SAR.";

        // Notify all users who have distributions in this commission
        $userIds = $commission->distributions()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $distribution = $commission->distributions()
                ->where('user_id', $userId)
                ->first();

            $userMessage = "Your commission of {$distribution->amount} SAR for unit {$commission->contractUnit->unit_number} has been confirmed.";

            UserNotification::create([
                'user_id' => $userId,
                'message' => $userMessage,
                'status' => 'pending',
            ]);
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyCommissionConfirmed($commission);
    }

    /**
     * Notify when a commission is received from the owner.
     */
    public function notifyCommissionReceived(Commission $commission): void
    {
        $message = "Commission payment of {$commission->net_amount} SAR for unit {$commission->contractUnit->unit_number} has been received from the owner.";

        // Notify all users who have distributions in this commission
        $userIds = $commission->distributions()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            $distribution = $commission->distributions()
                ->where('user_id', $userId)
                ->first();

            $userMessage = "Commission payment of {$distribution->amount} SAR for unit {$commission->contractUnit->unit_number} has been received and will be processed.";

            UserNotification::create([
                'user_id' => $userId,
                'message' => $userMessage,
                'status' => 'pending',
            ]);
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message);

        // Notify accounting department
        $this->accountingNotificationService->notifyCommissionReceivedFromOwner($commission);
    }

    /**
     * Notify when a commission distribution is approved.
     */
    public function notifyDistributionApproved(CommissionDistribution $distribution): void
    {
        if ($distribution->user_id) {
            $message = "Your commission distribution of {$distribution->amount} SAR ({$distribution->type}) has been approved.";

            UserNotification::create([
                'user_id' => $distribution->user_id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Notify when a commission distribution is rejected.
     */
    public function notifyDistributionRejected(CommissionDistribution $distribution): void
    {
        if ($distribution->user_id) {
            $reason = $distribution->notes ? " Reason: {$distribution->notes}" : '';
            $message = "Your commission distribution of {$distribution->amount} SAR ({$distribution->type}) has been rejected.{$reason}";

            UserNotification::create([
                'user_id' => $distribution->user_id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Notify when a deposit is refunded.
     */
    public function notifyDepositRefunded(Deposit $deposit): void
    {
        $message = "Deposit of {$deposit->amount} SAR for unit {$deposit->contractUnit->unit_number} has been refunded.";

        // Notify the marketing employee who made the reservation
        if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $deposit->salesReservation->marketing_employee_id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message);
    }

    /**
     * Notify when a deposit receipt is confirmed.
     */
    public function notifyDepositConfirmed(Deposit $deposit): void
    {
        $message = "Deposit receipt of {$deposit->amount} SAR for unit {$deposit->contractUnit->unit_number} has been confirmed.";

        // Notify the marketing employee who made the reservation
        if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
            UserNotification::create([
                'user_id' => $deposit->salesReservation->marketing_employee_id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);
    }

    /**
     * Notify sales managers.
     */
    protected function notifySalesManagers(string $message): void
    {
        $salesManagers = User::where('type', 'sales')
            ->where('is_manager', true)
            ->get();

        foreach ($salesManagers as $manager) {
            UserNotification::create([
                'user_id' => $manager->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Notify accountants.
     */
    protected function notifyAccountants(string $message): void
    {
        // Support both 'accountant' and 'accounting' types for backward compatibility
        $accountants = User::whereIn('type', ['accountant', 'accounting'])->get();

        foreach ($accountants as $accountant) {
            UserNotification::create([
                'user_id' => $accountant->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Notify project management managers.
     */
    protected function notifyProjectManagers(string $message): void
    {
        $projectManagers = User::where('type', 'project_management')
            ->where('is_manager', true)
            ->get();

        foreach ($projectManagers as $manager) {
            UserNotification::create([
                'user_id' => $manager->id,
                'message' => $message,
                'status' => 'pending',
            ]);
        }
    }
}
