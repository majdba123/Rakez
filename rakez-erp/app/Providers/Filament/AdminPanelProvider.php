<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\AdminHome;
use App\Filament\Admin\Pages\ApprovalsCenter;
use App\Filament\Admin\Pages\AccountingOverview;
use App\Filament\Admin\Pages\AiOverview;
use App\Filament\Admin\Pages\CreditOverview;
use App\Filament\Admin\Pages\HrOverview;
use App\Filament\Admin\Pages\InventoryOverview;
use App\Filament\Admin\Pages\MarketingOverview;
use App\Filament\Admin\Pages\ProjectsOverview;
use App\Filament\Admin\Pages\SalesOverview;
use App\Filament\Admin\Pages\WorkflowOverview;
use App\Filament\Admin\Widgets\GovernanceOverviewWidget;
use App\Filament\Admin\Widgets\PanelAccessSummaryWidget;
use App\Filament\Admin\Widgets\RecentAuditActivityWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id(config('governance.panel_id'))
            ->path(config('governance.panel_path'))
            ->login()
            ->authGuard('web')
            ->brandName('Rakez Governance')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->pages([
                AdminHome::class,
                ApprovalsCenter::class,
                CreditOverview::class,
                AccountingOverview::class,
                ProjectsOverview::class,
                SalesOverview::class,
                HrOverview::class,
                MarketingOverview::class,
                InventoryOverview::class,
                AiOverview::class,
                WorkflowOverview::class,
            ])
            ->widgets([
                GovernanceOverviewWidget::class,
                PanelAccessSummaryWidget::class,
                RecentAuditActivityWidget::class,
            ])
            ->navigationGroups([
                'Overview',
                'Access Governance',
                'Governance Observability',
                'Credit Oversight',
                'Accounting & Finance',
                'Contracts & Projects',
                'Sales Oversight',
                'HR Oversight',
                'Marketing Oversight',
                'Inventory Oversight',
                'AI & Knowledge',
                'Requests & Workflow',
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
