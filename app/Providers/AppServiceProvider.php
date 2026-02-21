<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\Deposit;
use App\Models\MarketingTask;
use App\Observers\ContractObserver;
use App\Observers\DepositObserver;
use App\Observers\LeadObserver;
use App\Observers\MarketingTaskObserver;
use App\Policies\LeadPolicy;
use App\Services\AI\VectorStore\DisabledVectorStore;
use App\Services\AI\VectorStore\JsonVectorStore;
use App\Services\AI\VectorStore\VectorStoreInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VectorStoreInterface::class, function () {
            $driver = config('ai_assistant.v2.rag.vector_driver', 'disabled');
            if ($driver === 'disabled') {
                return new DisabledVectorStore;
            }
            if ($driver === 'json') {
                return new JsonVectorStore($this->app->make(\App\Services\AI\OpenAIEmbeddingClient::class));
            }

            return new DisabledVectorStore;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Policies
        Gate::policy(\App\Models\Contract::class, \App\Policies\ContractPolicy::class);
        Gate::policy(\App\Models\ContractUnit::class, \App\Policies\ContractUnitPolicy::class);
        Gate::policy(\App\Models\SalesReservation::class, \App\Policies\SalesReservationPolicy::class);
        Gate::policy(\App\Models\SalesTarget::class, \App\Policies\SalesTargetPolicy::class);
        Gate::policy(\App\Models\MarketingTask::class, \App\Policies\MarketingTaskPolicy::class);
        Gate::policy(\App\Models\Task::class, \App\Policies\TaskPolicy::class);
        Gate::policy(\App\Models\MarketingProjectTeam::class, \App\Policies\MarketingProjectTeamPolicy::class);
        Gate::policy(\App\Models\SalesAttendanceSchedule::class, \App\Policies\SalesAttendancePolicy::class);
        Gate::policy(\App\Models\Commission::class, \App\Policies\CommissionPolicy::class);
        Gate::policy(\App\Models\Deposit::class, \App\Policies\DepositPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);

        // AI v2 self-feeding: observers dispatch IndexRecordSummaryJob afterCommit
        Lead::observe(LeadObserver::class);
        Contract::observe(ContractObserver::class);
        MarketingTask::observe(MarketingTaskObserver::class);
        Deposit::observe(DepositObserver::class);

        // Define custom gates for commission and deposit operations (sales_leader aligned with app role)
        Gate::define('approve-commission-distribution', function ($user) {
            return $user->hasAnyRole(['admin', 'sales_leader', 'sales_manager']);
        });

        Gate::define('approve-commission', function ($user) {
            return $user->hasAnyRole(['admin', 'sales_leader', 'sales_manager']);
        });

        Gate::define('mark-commission-paid', function ($user) {
            return $user->hasAnyRole(['admin', 'accountant']);
        });

        Gate::define('confirm-deposit-receipt', function ($user) {
            return $user->hasAnyRole(['admin', 'accountant', 'sales_leader', 'sales_manager']);
        });

        Gate::define('refund-deposit', function ($user) {
            return $user->hasAnyRole(['admin', 'accountant', 'sales_leader', 'sales_manager']);
        });

        // Implicitly grant "Super Admin" role all permissions
        // This works in the app by using gate-related functions like auth()->user->can() and @can()
        Gate::before(function ($user, $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }

            // Support dynamic permissions (ex: project_management managers via is_manager flag)
            if (method_exists($user, 'hasEffectivePermission') && $user->hasEffectivePermission($ability)) {
                return true;
            }

            return null;
        });

        $this->configureRateLimiting();
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     */
    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api.php'));
    }

    protected function configureRateLimiting(): void
    {
        $perMinute = (int) config('ai_assistant.rate_limits.per_minute', 60);

        RateLimiter::for('ai-assistant', function (Request $request) use ($perMinute) {
            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });
    }
}
