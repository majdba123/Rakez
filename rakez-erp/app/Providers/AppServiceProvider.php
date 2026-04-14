<?php

namespace App\Providers;

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
        $this->configureWritableBladeCompiledPath();
    }

    /**
     * Blade must compile to disk. If storage/framework/views is not writable (common on locked-down hosts),
     * use the system temp dir so you do not need chmod/chown on the server.
     */
    protected function configureWritableBladeCompiledPath(): void
    {
        $preferred = env('VIEW_COMPILED_PATH', storage_path('framework/views'));

        if (!is_dir($preferred)) {
            @mkdir($preferred, 0755, true);
        }

        if (is_writable($preferred)) {
            config(['view.compiled' => $preferred]);

            return;
        }

        $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rakez-blade-' . md5((string) base_path());

        if (!is_dir($fallback)) {
            @mkdir($fallback, 0755, true);
        }

        if (is_writable($fallback)) {
            config(['view.compiled' => $fallback]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureStorageDirectoriesExist();

        // Register Policies
        Gate::policy(\App\Models\Contract::class, \App\Policies\ContractPolicy::class);
        Gate::policy(\App\Models\ContractUnit::class, \App\Policies\ContractUnitPolicy::class);
        Gate::policy(\App\Models\SalesReservation::class, \App\Policies\SalesReservationPolicy::class);
        Gate::policy(\App\Models\SalesTarget::class, \App\Policies\SalesTargetPolicy::class);
        Gate::policy(\App\Models\MarketingTask::class, \App\Policies\MarketingTaskPolicy::class);
        Gate::policy(\App\Models\MarketingProjectTeam::class, \App\Policies\MarketingProjectTeamPolicy::class);
        Gate::policy(\App\Models\SalesAttendanceSchedule::class, \App\Policies\SalesAttendancePolicy::class);
        Gate::policy(\App\Models\Commission::class, \App\Policies\CommissionPolicy::class);
        Gate::policy(\App\Models\Deposit::class, \App\Policies\DepositPolicy::class);

        // Define custom gates for commission and deposit operations
        Gate::define('approve-commission-distribution', function ($user) {
            return $user->hasAnyRole(['admin', 'sales_leader']);
        });

        Gate::define('approve-commission', function ($user) {
            return $user->hasAnyRole(['admin', 'sales_leader']);
        });

        Gate::define('mark-commission-paid', function ($user) {
            return $user->hasAnyRole(['admin', 'accounting']);
        });

        Gate::define('confirm-deposit-receipt', function ($user) {
            return $user->hasAnyRole(['admin', 'accounting', 'sales_leader']);
        });

        Gate::define('refund-deposit', function ($user) {
            return $user->hasAnyRole(['admin', 'accounting', 'sales_leader']);
        });

        Gate::define('viewTargetsByProject', function ($user, int $contractId) {
            return app(\App\Policies\SalesTargetPolicy::class)->viewTargetsByProject($user, $contractId);
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
     * Ensure PDF and font storage directories exist (avoids mPDF warnings and font path issues).
     */
    protected function ensureStorageDirectoriesExist(): void
    {
        $dirs = [
            storage_path('fonts'),
            storage_path('app/mpdf'),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
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

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}