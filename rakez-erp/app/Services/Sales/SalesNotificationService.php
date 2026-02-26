<?php

namespace App\Services\Sales;

use App\Models\UserNotification;
use App\Models\AdminNotification;
use App\Models\Contract;
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
        $this->createNotification(
            $reservation->marketing_employee_id,
            $message,
            'unit_reserved',
            [
                'reservation_id' => $reservation->id,
                'unit_id' => $reservation->contract_unit_id,
                'contract_id' => $reservation->contract_id,
            ]
        );

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message, 'unit_reserved');

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
            $this->createNotification(
                $deposit->salesReservation->marketing_employee_id,
                $message,
                'deposit_received',
                [
                    'deposit_id' => $deposit->id,
                    'reservation_id' => $deposit->sales_reservation_id,
                    'unit_id' => $deposit->contract_unit_id,
                ]
            );
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message, 'deposit_received');

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
        $this->createNotification(
            $reservation->marketing_employee_id,
            $message,
            'unit_vacated',
            [
                'reservation_id' => $reservation->id,
                'unit_id' => $reservation->contract_unit_id,
                'contract_id' => $reservation->contract_id,
            ]
        );

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message, 'unit_vacated');

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
        $this->createNotification(
            $reservation->marketing_employee_id,
            $message,
            'reservation_canceled',
            [
                'reservation_id' => $reservation->id,
                'unit_id' => $reservation->contract_unit_id,
                'contract_id' => $reservation->contract_id,
            ]
        );

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify sales managers
        $this->notifySalesManagers($message, 'reservation_canceled');

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

            $this->createNotification(
                $userId,
                $userMessage,
                'commission_confirmed',
                [
                    'commission_id' => $commission->id,
                    'unit_id' => $commission->contract_unit_id,
                    'distribution_id' => $distribution?->id,
                ]
            );
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

            $this->createNotification(
                $userId,
                $userMessage,
                'commission_received_from_owner',
                [
                    'commission_id' => $commission->id,
                    'unit_id' => $commission->contract_unit_id,
                    'distribution_id' => $distribution?->id,
                ]
            );
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message, 'commission_received_from_owner');

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

            $this->createNotification(
                $distribution->user_id,
                $message,
                'commission_distribution_approved',
                [
                    'distribution_id' => $distribution->id,
                    'commission_id' => $distribution->commission_id,
                    'type' => $distribution->type,
                ]
            );
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

            $this->createNotification(
                $distribution->user_id,
                $message,
                'commission_distribution_rejected',
                [
                    'distribution_id' => $distribution->id,
                    'commission_id' => $distribution->commission_id,
                    'type' => $distribution->type,
                ]
            );
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
            $this->createNotification(
                $deposit->salesReservation->marketing_employee_id,
                $message,
                'deposit_refunded',
                [
                    'deposit_id' => $deposit->id,
                    'reservation_id' => $deposit->sales_reservation_id,
                    'unit_id' => $deposit->contract_unit_id,
                ]
            );
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);

        // Notify accountants
        $this->notifyAccountants($message, 'deposit_refunded');
    }

    /**
     * Notify when a deposit receipt is confirmed.
     */
    public function notifyDepositConfirmed(Deposit $deposit): void
    {
        $message = "Deposit receipt of {$deposit->amount} SAR for unit {$deposit->contractUnit->unit_number} has been confirmed.";

        // Notify the marketing employee who made the reservation
        if ($deposit->salesReservation && $deposit->salesReservation->marketing_employee_id) {
            $this->createNotification(
                $deposit->salesReservation->marketing_employee_id,
                $message,
                'deposit_confirmed',
                [
                    'deposit_id' => $deposit->id,
                    'reservation_id' => $deposit->sales_reservation_id,
                    'unit_id' => $deposit->contract_unit_id,
                ]
            );
        }

        // Notify all admins
        AdminNotification::notifyAllAdmins($message);
    }

    /**
     * Notify sales managers.
     */
    protected function notifySalesManagers(string $message, string $eventType = 'sales_event', array $context = []): void
    {
        $salesManagers = User::where('type', 'sales')
            ->where('is_manager', true)
            ->get();

        foreach ($salesManagers as $manager) {
            $this->createNotification($manager->id, $message, $eventType, $context);
        }
    }

    /**
     * Notify accountants.
     */
    protected function notifyAccountants(string $message, string $eventType = 'accounting_event', array $context = []): void
    {
        // Support both 'accountant' and 'accounting' types for backward compatibility
        $accountants = User::whereIn('type', ['accountant', 'accounting'])->get();

        foreach ($accountants as $accountant) {
            $this->createNotification($accountant->id, $message, $eventType, $context);
        }
    }

    /**
     * Notify project management managers.
     */
    protected function notifyProjectManagers(string $message, string $eventType = 'project_event', array $context = []): void
    {
        $projectManagers = User::where('type', 'project_management')
            ->where('is_manager', true)
            ->get();

        foreach ($projectManagers as $manager) {
            $this->createNotification($manager->id, $message, $eventType, $context);
        }
    }

    /**
     * Notify a team member that they have been scheduled to work at a project.
     */
    public function notifyScheduleAssigned(User $user, Contract $contract, string $date, ?string $startTime, ?string $endTime): void
    {
        $projectName = $contract->project_name ?? 'Unknown Project';
        $timeInfo = ($startTime && $endTime)
            ? " from {$startTime} to {$endTime}"
            : '';

        $message = "You are assigned to {$projectName} on {$date}{$timeInfo}.";

        $this->createNotification(
            $user->id,
            $message,
            'schedule_assigned',
            [
                'contract_id' => $contract->id,
                'date' => $date,
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]
        );
    }

    /**
     * Notify a team member that their schedule at a project has been cancelled.
     */
    public function notifyScheduleRemoved(User $user, Contract $contract, string $date): void
    {
        $projectName = $contract->project_name ?? 'Unknown Project';

        $message = "Your schedule at {$projectName} on {$date} has been cancelled.";

        $this->createNotification(
            $user->id,
            $message,
            'schedule_removed',
            [
                'contract_id' => $contract->id,
                'date' => $date,
            ]
        );
    }

    protected function createNotification(int $userId, string $message, string $eventType, array $context = []): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'message' => $message,
            'status' => 'pending',
            'event_type' => $eventType,
            'context' => $context ?: null,
        ]);
    }
}
