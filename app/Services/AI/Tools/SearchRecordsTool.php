<?php

namespace App\Services\AI\Tools;

use App\Models\Contract;
use App\Models\Lead;
use App\Models\MarketingTask;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class SearchRecordsTool implements ToolContract
{
    public function __invoke(User $user, array $args): array
    {
        $query = trim((string) Arr::get($args, 'query', ''));
        $modules = Arr::get($args, 'modules', []);
        $limit = min(20, max(1, (int) Arr::get($args, 'limit', 10)));
        $sourceRefs = [];
        $hits = [];

        if (in_array('leads', $modules) && Gate::forUser($user)->allows('viewAny', Lead::class)) {
            $leads = Lead::query()
                ->when($query, fn ($q) => $q->where('name', 'like', "%{$query}%")->orWhere('contact_info', 'like', "%{$query}%"))
                ->limit($limit)
                ->get();
            foreach ($leads as $lead) {
                if (Gate::forUser($user)->allows('view', $lead)) {
                    $hits[] = ['module' => 'lead', 'id' => $lead->id, 'title' => $lead->name, 'summary' => mb_substr($lead->contact_info ?? '', 0, 100)];
                    $sourceRefs[] = ['type' => 'record', 'title' => 'Lead: '.$lead->name, 'ref' => "lead/{$lead->id}"];
                }
            }
        }

        if (in_array('projects', $modules) || in_array('contracts', $modules)) {
            $contracts = Contract::query()
                ->when($query, fn ($q) => $q->where('project_name', 'like', "%{$query}%")->orWhere('developer_name', 'like', "%{$query}%"))
                ->limit($limit)
                ->get();
            foreach ($contracts as $c) {
                if (Gate::forUser($user)->allows('view', $c)) {
                    $hits[] = ['module' => 'project', 'id' => $c->id, 'title' => $c->project_name, 'summary' => $c->developer_name ?? ''];
                    $sourceRefs[] = ['type' => 'record', 'title' => 'Project: '.$c->project_name, 'ref' => "contract/{$c->id}"];
                }
            }
        }

        if (in_array('marketing_tasks', $modules) && Gate::forUser($user)->allows('viewAny', MarketingTask::class)) {
            $tasks = MarketingTask::query()
                ->when($query, fn ($q) => $q->where('task_name', 'like', "%{$query}%"))
                ->limit($limit)
                ->get();
            foreach ($tasks as $t) {
                if (Gate::forUser($user)->allows('view', $t)) {
                    $title = $t->task_name ?? 'Task';
                    $hits[] = ['module' => 'marketing_task', 'id' => $t->id, 'title' => $title, 'summary' => ''];
                    $sourceRefs[] = ['type' => 'record', 'title' => 'Task: '.$title, 'ref' => "marketing_task/{$t->id}"];
                }
            }
        }

        return [
            'result' => ['hits' => array_slice($hits, 0, $limit), 'count' => count($hits)],
            'source_refs' => $sourceRefs,
        ];
    }
}
