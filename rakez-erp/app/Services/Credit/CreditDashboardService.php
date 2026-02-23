<?php

namespace App\Services\Credit;

use App\Models\SalesReservation;
use App\Models\SalesWaitingList;
use App\Models\CreditFinancingTracker;
use App\Models\TitleTransfer;
use App\Services\Credit\CreditFinancingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreditDashboardService
{
    /**
     * Get all dashboard KPIs.
     */
    public function getKpis(): array
    {
        return Cache::remember('credit_dashboard_kpis', 60, function () {
            return [
                'confirmed_bookings_count' => $this->getConfirmedBookingsCount(),
                'negotiation_bookings_count' => $this->getNegotiationBookingsCount(),
                'waiting_bookings_count' => $this->getWaitingBookingsCount(),
                'requires_review_count' => $this->getRequiresReviewCount(),
                'rejected_with_paid_down_payment_count' => $this->getRejectedWithPaidDownPaymentCount(),
                'projects_in_progress_count' => $this->getProjectsInProgressCount(),
                'rejected_by_bank_count' => $this->getRejectedByBankCount(),
                'overdue_stages' => $this->getOverdueStagesSummary(),
                'pending_accounting_confirmation' => $this->getPendingAccountingConfirmationCount(),
                'in_title_transfer_count' => $this->getInTitleTransferCount(),
                'sold_projects_count' => $this->getSoldProjectsCount(),
            ];
        });
    }

    /**
     * Get confirmed bookings count.
     */
    public function getConfirmedBookingsCount(): int
    {
        return SalesReservation::confirmedForCredit()
            ->whereIn('credit_status', ['pending', 'in_progress'])
            ->count();
    }

    /**
     * Get negotiation bookings count.
     */
    public function getNegotiationBookingsCount(): int
    {
        return SalesReservation::where('status', 'under_negotiation')
            ->where('reservation_type', 'negotiation')
            ->count();
    }

    /**
     * Get waiting bookings count.
     */
    public function getWaitingBookingsCount(): int
    {
        return SalesWaitingList::where('status', 'waiting')->count();
    }

    /**
     * Get count of reservations requiring review (have overdue stages).
     */
    public function getRequiresReviewCount(): int
    {
        return CreditFinancingTracker::inProgress()
            ->withOverdueStages()
            ->count();
    }

    /**
     * Get count of rejected financing with paid down payments.
     */
    public function getRejectedWithPaidDownPaymentCount(): int
    {
        return SalesReservation::where('credit_status', 'rejected')
            ->where('down_payment_confirmed', true)
            ->count();
    }

    /**
     * Get count of projects in progress (confirmed reservations with financing in progress).
     */
    public function getProjectsInProgressCount(): int
    {
        return SalesReservation::confirmedForCredit()
            ->where('credit_status', 'in_progress')
            ->count();
    }

    /**
     * Get count of all reservations rejected by bank (all credit_status = rejected).
     */
    public function getRejectedByBankCount(): int
    {
        return SalesReservation::where('credit_status', 'rejected')->count();
    }

    /**
     * Get summary of overdue stages.
     */
    public function getOverdueStagesSummary(): array
    {
        $summary = [];
        for ($i = 1; $i <= 5; $i++) {
            $summary["stage_{$i}"] = CreditFinancingTracker::inProgress()
                ->where("stage_{$i}_status", 'overdue')
                ->count();
        }
        return $summary;
    }

    /**
     * Get count pending accounting confirmation.
     */
    public function getPendingAccountingConfirmationCount(): int
    {
        return SalesReservation::pendingAccountingConfirmation()->count();
    }

    /**
     * Get count of reservations in title transfer stage.
     */
    public function getInTitleTransferCount(): int
    {
        return SalesReservation::where('credit_status', 'title_transfer')->count();
    }

    /**
     * Get count of sold projects.
     */
    public function getSoldProjectsCount(): int
    {
        return SalesReservation::soldProjects()->count();
    }

    /**
     * Get Arabic labels for financing stages 1-5 (for display in dashboard).
     */
    public function getStageLabelsAr(): array
    {
        $names = CreditFinancingService::STAGE_NAMES;
        $out = [];
        foreach ($names as $num => $label) {
            $out["stage_{$num}"] = $label;
        }
        return $out;
    }

    /**
     * Get title transfer breakdown (stages 6 & 7: preparation, contract execution).
     */
    public function getTitleTransferBreakdown(): array
    {
        return [
            'preparation_count' => TitleTransfer::where('status', 'preparation')->count(),
            'scheduled_count' => TitleTransfer::where('status', 'scheduled')->count(),
        ];
    }

    /**
     * Get detailed stage breakdown.
     */
    public function getStageBreakdown(): array
    {
        $breakdown = [];

        for ($i = 1; $i <= 5; $i++) {
            $breakdown["stage_{$i}"] = [
                'pending' => CreditFinancingTracker::inProgress()->where("stage_{$i}_status", 'pending')->count(),
                'in_progress' => CreditFinancingTracker::inProgress()->where("stage_{$i}_status", 'in_progress')->count(),
                'completed' => CreditFinancingTracker::where("stage_{$i}_status", 'completed')->count(),
                'overdue' => CreditFinancingTracker::inProgress()->where("stage_{$i}_status", 'overdue')->count(),
            ];
        }

        return $breakdown;
    }

    /**
     * Clear dashboard cache.
     */
    public function clearCache(): void
    {
        Cache::forget('credit_dashboard_kpis');
    }
}

