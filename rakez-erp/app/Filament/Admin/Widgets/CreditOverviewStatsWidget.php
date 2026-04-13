<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Concerns\HasGovernanceAuthorization;
use App\Models\ClaimFile;
use App\Models\TitleTransfer;
use App\Services\Credit\CreditDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditOverviewStatsWidget extends StatsOverviewWidget
{
    use HasGovernanceAuthorization;

    public static function canView(): bool
    {
        return static::canAccessGovernancePage('Credit Oversight', 'credit.dashboard.view');
    }

    protected function getStats(): array
    {
        $kpis = app(CreditDashboardService::class)->getKpis();

        $scheduledTransfers = TitleTransfer::where('status', 'scheduled')->count();
        $completedTransfers = TitleTransfer::where('status', 'completed')->count();
        $claimFilesCount = ClaimFile::count();

        return [
            Stat::make('Confirmed Bookings', (string) ($kpis['confirmed_bookings_count'] ?? 0))
                ->description('Under credit review')
                ->color('primary'),
            Stat::make('Needs Review', (string) ($kpis['requires_review_count'] ?? 0))
                ->description('Overdue financing stages')
                ->color(($kpis['requires_review_count'] ?? 0) > 0 ? 'danger' : 'gray'),
            Stat::make('In Progress', (string) ($kpis['projects_in_progress_count'] ?? 0))
                ->description('Active financing trackers'),
            Stat::make('Title Transfers', "{$scheduledTransfers} / {$completedTransfers}")
                ->description('Scheduled / Completed'),
            Stat::make('Sold Projects', (string) ($kpis['sold_projects_count'] ?? 0))
                ->description('Completed title transfers')
                ->color('success'),
            Stat::make('Pending Accounting', (string) ($kpis['pending_accounting_confirmation'] ?? 0))
                ->description('Awaiting accounting confirmation')
                ->color(($kpis['pending_accounting_confirmation'] ?? 0) > 0 ? 'warning' : 'gray'),
            Stat::make('Rejected + Paid Deposit', (string) ($kpis['rejected_with_paid_down_payment_count'] ?? 0))
                ->description('Rejected with confirmed down payment')
                ->color(($kpis['rejected_with_paid_down_payment_count'] ?? 0) > 0 ? 'danger' : 'gray'),
            Stat::make('Claim Files', (string) $claimFilesCount)
                ->description('Total generated claim files'),
        ];
    }
}
