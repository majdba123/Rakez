<?php

namespace App\Services\AI;

use App\Models\AdminNotification;
use App\Models\Contract;
use App\Models\ContractUnit;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Dashboard\ProjectManagementDashboardService;
use App\Services\Marketing\MarketingDashboardService;
use App\Services\Marketing\MarketingProjectService;
use App\Services\Marketing\MarketingTaskService;
use Illuminate\Support\Str;

class ContextBuilder
{
    public function __construct(
        private readonly ProjectManagementDashboardService $dashboardService,
        private readonly MarketingDashboardService $marketingDashboardService,
        private readonly MarketingProjectService $marketingProjectService,
        private readonly MarketingTaskService $marketingTaskService,
        private readonly ContextValidator $contextValidator,
        private readonly SectionRegistry $sectionRegistry
    ) {}

    public function build(User $user, ?string $sectionKey, array $capabilities, array $context): array
    {
        // Validate context parameters using schema
        $validatedContext = $this->contextValidator->validate($sectionKey, $context);

        // Check context policies before building context
        $this->checkContextPolicies($user, $sectionKey, $validatedContext, $capabilities);

        $data = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'type' => $user->type,
            ],
            'section' => $sectionKey,
        ];

        if (in_array('contracts.view', $capabilities, true)) {
            $data['contracts_summary'] = $this->buildContractsSummary($user, $capabilities);
        }

        if ($sectionKey === 'contracts' && isset($validatedContext['contract_id'])) {
            $contractData = $this->buildContractDetails($user, (int) $validatedContext['contract_id'], $capabilities);
            if ($contractData) {
                $data['contract'] = $contractData;
            }
        }

        if (in_array('notifications.view', $capabilities, true)) {
            $data['notifications_summary'] = $this->buildNotificationsSummary($user, $capabilities);
        }

        if ($sectionKey === 'dashboard' && in_array('dashboard.analytics.view', $capabilities, true)) {
            $data['dashboard'] = $this->dashboardService->getDashboardStatistics();
        }

        if ($sectionKey === 'marketing_dashboard' && in_array('marketing.dashboard.view', $capabilities, true)) {
            $data['marketing_dashboard'] = $this->marketingDashboardService->getDashboardKPIs();
        }

        if ($sectionKey === 'marketing_projects' && in_array('marketing.projects.view', $capabilities, true)) {
            if (isset($validatedContext['contract_id'])) {
                $data['marketing_project'] = $this->marketingProjectService->getProjectDetails((int) $validatedContext['contract_id']);
            } else {
                $data['marketing_projects_list'] = $this->marketingProjectService->getProjectsWithCompletedContracts();
            }
        }

        if ($sectionKey === 'marketing_tasks' && in_array('marketing.tasks.view', $capabilities, true)) {
            $data['marketing_tasks'] = $this->marketingTaskService->getDailyTasks($user->id);
        }

        return $data;
    }

    private function checkContextPolicies(User $user, ?string $sectionKey, array $context, array $capabilities): void
    {
        $policies = $this->sectionRegistry->contextPolicy($sectionKey);

        if (empty($policies)) {
            return;
        }

        foreach ($policies as $param => $policy) {
            if (! isset($context[$param])) {
                continue;
            }

            $value = $context[$param];

            // Map policy to capability check
            // For now, we'll do basic checks - can be extended later
            switch ($policy) {
                case 'view-contract':
                    if (isset($context['contract_id'])) {
                        $contract = Contract::query()->find((int) $context['contract_id']);
                        if ($contract && ! $contract->isOwnedBy($user->id) && ! in_array('contracts.view_all', $capabilities, true)) {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                response()->json(['error' => 'Unauthorized access to contract'], 403)
                            );
                        }
                    }
                    break;
                case 'view-unit':
                    if (isset($context['unit_id'])) {
                        $unit = ContractUnit::query()->with('secondPartyData.contract')->find((int) $context['unit_id']);
                        if ($unit && $unit->secondPartyData?->contract) {
                            $contract = $unit->secondPartyData->contract;
                            $allowed = $contract->isOwnedBy($user->id)
                                || in_array('contracts.view_all', $capabilities, true);
                            if (! $allowed) {
                                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                    response()->json(['error' => 'Unauthorized access to unit'], 403)
                                );
                            }
                        }
                    }
                    break;
            }
        }
    }

    private function buildContractsSummary(User $user, array $capabilities): array
    {
        $query = Contract::query();
        if (! in_array('contracts.view_all', $capabilities, true)) {
            $query->where('user_id', $user->id);
        }

        $total = $query->count();
        $latest = $query->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'project_name', 'status', 'updated_at'])
            ->map(fn (Contract $contract) => [
                'id' => $contract->id,
                'project_name' => $this->sanitizeText($contract->project_name),
                'status' => $contract->status,
                'updated_at' => optional($contract->updated_at)->toDateTimeString(),
            ])
            ->toArray();

        return [
            'total' => $total,
            'latest' => $latest,
        ];
    }

    private function buildContractDetails(User $user, int $contractId, array $capabilities): ?array
    {
        $contract = Contract::query()->find($contractId);
        if (! $contract) {
            return null;
        }

        $canView = $contract->isOwnedBy($user->id)
            || in_array('contracts.view_all', $capabilities, true);

        if (! $canView) {
            return null;
        }

        return [
            'id' => $contract->id,
            'project_name' => $this->sanitizeText($contract->project_name),
            'status' => $contract->status,
            'units_count' => $contract->units_count ?? null,
            'total_units_value' => $contract->total_units_value ?? null,
            'updated_at' => optional($contract->updated_at)->toDateTimeString(),
        ];
    }

    private function buildNotificationsSummary(User $user, array $capabilities): array
    {
        $userNotifications = UserNotification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'message', 'status', 'created_at'])
            ->map(fn (UserNotification $notification) => [
                'id' => $notification->id,
                'message' => $this->sanitizeText($notification->message),
                'status' => $notification->status,
                'created_at' => optional($notification->created_at)->toDateTimeString(),
            ])
            ->toArray();

        $data = [
            'latest' => $userNotifications,
        ];

        if (in_array('notifications.manage', $capabilities, true)) {
            $adminNotifications = AdminNotification::query()
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'message', 'status', 'created_at'])
                ->map(fn (AdminNotification $notification) => [
                    'id' => $notification->id,
                    'message' => $this->sanitizeText($notification->message),
                    'status' => $notification->status,
                    'created_at' => optional($notification->created_at)->toDateTimeString(),
                ])
                ->toArray();

            $data['admin_latest'] = $adminNotifications;
        }

        return $data;
    }

    private function sanitizeText(?string $value, int $maxLength = 240): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = strip_tags($value);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized ?? '');
        $sanitized = trim($sanitized);

        return Str::limit($sanitized, $maxLength);
    }
}
