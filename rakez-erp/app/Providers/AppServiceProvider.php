<?php

namespace App\Providers;

use App\Services\AI\Realtime\OpenAiRealtimeWebSocketClient;
use App\Services\AI\Realtime\RealtimeTransportClient;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(RealtimeTransportClient::class, OpenAiRealtimeWebSocketClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->ensureStorageDirectoriesExist();
        $this->freezeClockFromEnvironment();

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

        // Operational admin bypass: the legacy 'admin' role gets full access
        // to operational abilities, but governance abilities (admin.* / governance.*)
        // are handled exclusively by GovernanceAccessService — no Gate bypass.
        Gate::before(function ($user, $ability) {
            if (\Illuminate\Support\Str::startsWith($ability, ['admin.', 'governance.'])) {
                return null;
            }

            if ($user->hasRole('admin')) {
                return true;
            }

            if (method_exists($user, 'hasEffectivePermission') && $user->hasEffectivePermission($ability)) {
                return true;
            }

            return null;
        });

        $this->configureRateLimiting();
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

    protected function freezeClockFromEnvironment(): void
    {
        $frozenNow = env('APP_FROZEN_NOW');

        if (filled($frozenNow)) {
            Carbon::setTestNow(Carbon::parse($frozenNow));
        }
    }

    protected function configureRateLimiting(): void
    {
        $perMinute = (int) config('ai_assistant.rate_limits.per_minute', 60);
        $realtimeCreatePerMinute = (int) config('ai_realtime.rate_limits.session_create_per_minute', 3);
        $realtimeControlPerMinute = (int) config('ai_realtime.rate_limits.control_events_per_minute', 60);

        RateLimiter::for('ai-assistant', function (Request $request) use ($perMinute) {
            return Limit::perMinute($perMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-realtime-create', function (Request $request) use ($realtimeCreatePerMinute) {
            return Limit::perMinute($realtimeCreatePerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('ai-realtime-control', function (Request $request) use ($realtimeControlPerMinute) {
            return Limit::perMinute($realtimeControlPerMinute)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}