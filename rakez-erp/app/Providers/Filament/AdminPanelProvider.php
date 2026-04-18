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
use App\Http\Middleware\SetFilamentAdminLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->brandName(fn (): string => __('filament-admin.panel.brand_name'))
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
                'Overview' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.overview')),
                'Access Governance' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.access_governance')),
                'Governance Observability' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.governance_observability')),
                'Credit Oversight' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.credit_oversight')),
                'Accounting & Finance' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.accounting_finance')),
                'Contracts & Projects' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.contracts_projects')),
                'Sales Oversight' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.sales_oversight')),
                'HR Oversight' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.hr_oversight')),
                'Marketing Oversight' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.marketing_oversight')),
                'Inventory Oversight' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.inventory_oversight')),
                'AI & Knowledge' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.ai_knowledge')),
                'Requests & Workflow' => NavigationGroup::make(fn (): string => __('filament-admin.navigation.groups.requests_workflow')),
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, function (): HtmlString {
                if (! app()->isLocale('ar')) {
                    return new HtmlString('');
                }

                return new HtmlString(<<<'HTML'
<script>
document.documentElement.setAttribute('dir', 'rtl');
document.documentElement.setAttribute('lang', 'ar');
</script>
<style>
html[dir="rtl"] .fi-layout,
html[dir="rtl"] .fi-page,
html[dir="rtl"] .fi-sidebar,
html[dir="rtl"] .fi-topbar,
html[dir="rtl"] .fi-ta,
html[dir="rtl"] .fi-fo-field-wrp {
    direction: rtl;
}

html[dir="rtl"] .fi-sidebar,
html[dir="rtl"] .fi-main,
html[dir="rtl"] .fi-page {
    text-align: right;
}
</style>
HTML);
            })
            ->middleware([
                SetFilamentAdminLocale::class,
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
