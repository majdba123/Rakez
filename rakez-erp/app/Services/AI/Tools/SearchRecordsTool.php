<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Models\User;
use Throwable;

class SearchRecordsTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        if (! $user->can('use-ai-assistant')) {
            return ToolResponse::denied('use-ai-assistant');
        }

        $query = $args['query'] ?? '';
        $modules = $args['modules'] ?? ['leads'];
        $limit = $args['limit'] ?? 10;

        if ($query === '') {
            return ToolResponse::invalidArguments('Search query is required.');
        }

        if (! is_array($modules) || $modules === []) {
            return ToolResponse::invalidArguments('modules must be a non-empty array.');
        }

        $permissionByModule = [
            'leads' => 'leads.view',
            'projects' => 'contracts.view',
            'contracts' => 'contracts.view',
            'marketing_tasks' => 'marketing.tasks.view',
            'customers' => 'second_party_data.view',
        ];

        foreach ($modules as $module) {
            $perm = $permissionByModule[$module] ?? null;
            if ($perm === null) {
                return ToolResponse::invalidArguments("Unknown module '{$module}'.");
            }
            if (! $user->can($perm)) {
                return ToolResponse::denied($perm);
            }
        }

        $results = [];
        $sourceRefs = [];

        try {
            foreach ($modules as $module) {
                $moduleResults = match ($module) {
                    'leads' => $this->searchLeads($user, $query, $limit),
                    'projects', 'contracts' => $this->searchProjects($user, $query, $limit),
                    'marketing_tasks' => $this->searchMarketingTasks($user, $query, $limit),
                    'customers' => $this->searchCustomers($user, $query, $limit),
                    default => [],
                };

                $results[$module] = $moduleResults;

                foreach ($moduleResults as $item) {
                    $sourceRefs[] = [
                        'type' => 'record',
                        'title' => $item['label'] ?? "{$module} #{$item['id']}",
                        'ref' => "{$module}:{$item['id']}",
                    ];
                }
            }
        } catch (Throwable $e) {
            return ToolResponse::error('Search failed: '.$e->getMessage());
        }

        $data = array_merge(
            [
                'summary' => 'نتائج بحث نصّي ضمن صلاحياتك لكل وحدة (ليدات، عقود، مهام تسويق، عملاء).',
                'warnings' => [
                    'كل وحدة تتطلّب صلاحية مستقلة (leads.view، contracts.view، marketing.tasks.view، second_party_data.view).',
                    'صلاحية المساعد الذكي وحدها لا تكفي للوصول إلى البيانات دون هذه الصلاحيات.',
                ],
            ],
            $results
        );

        return ToolResponse::success('tool_search_records', [
            'query' => $query,
            'modules' => $modules,
        ], $data, $sourceRefs);
    }

    private function searchLeads(User $user, string $query, int $limit): array
    {
        $builder = Lead::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('contact_info', 'LIKE', "%{$query}%")
                ->orWhere('source', 'LIKE', "%{$query}%");
        });

        if (! $user->can('leads.view_all')) {
            $builder->where('assigned_to', $user->id);
        }

        return $builder->limit($limit)->get()->map(fn ($l) => [
            'id' => $l->id,
            'label' => $l->name,
            'status' => $l->status,
            'source' => $l->source,
            'assigned_to' => $l->assigned_to,
        ])->toArray();
    }

    private function searchProjects(User $user, string $query, int $limit): array
    {
        $builder = Contract::with('city')->where(function ($q) use ($query) {
            $q->where('project_name', 'LIKE', "%{$query}%")
                ->orWhere('developer_name', 'LIKE', "%{$query}%")
                ->orWhereHas('city', function ($cq) use ($query) {
                    $cq->where('name', 'LIKE', "%{$query}%");
                });
        });

        if (! $user->can('contracts.view_all')) {
            $builder->where('user_id', $user->id);
        }

        return $builder->limit($limit)->get()->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->project_name,
            'developer' => $c->developer_name,
            'city' => $c->city?->name,
            'status' => $c->status,
        ])->toArray();
    }

    private function searchMarketingTasks(User $user, string $query, int $limit): array
    {
        return MarketingTask::where('title', 'LIKE', "%{$query}%")
            ->limit($limit)->get()->map(fn ($t) => [
                'id' => $t->id,
                'label' => $t->title,
                'status' => $t->status ?? null,
            ])->toArray();
    }

    private function searchCustomers(User $user, string $query, int $limit): array
    {
        return \App\Models\SecondPartyData::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('phone', 'LIKE', "%{$query}%");
        })->limit($limit)->get()->map(fn ($c) => [
            'id' => $c->id,
            'label' => $c->name,
            'phone' => $c->phone,
        ])->toArray();
    }
}
