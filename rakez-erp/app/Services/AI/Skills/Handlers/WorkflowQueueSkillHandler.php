<?php

namespace App\Services\AI\Skills\Handlers;

use App\Models\User;
use App\Services\AI\Skills\Contracts\SkillHandlerContract;
use App\Services\Workflow\WorkflowTaskAdminService;

class WorkflowQueueSkillHandler implements SkillHandlerContract
{
    public function __construct(
        private readonly WorkflowTaskAdminService $workflowTaskService,
    ) {}

    public function execute(User $user, array $definition, array $input, array $context): array
    {
        $perPage = isset($input['per_page']) ? (int) $input['per_page'] : 10;
        $status = isset($input['status']) ? (string) $input['status'] : null;

        $assigned = $this->workflowTaskService->getAssignedTasksForAiSkill($user, $perPage, $status);
        $requested = $this->workflowTaskService->getRequestedTasksForAiSkill($user, $perPage, $status);

        return [
            'status' => 'ok',
            'data' => [
                'assigned_summary' => [
                    'total' => $assigned['total'],
                    'items' => $assigned['items'],
                ],
                'requested_summary' => [
                    'total' => $requested['total'],
                    'items' => $requested['items'],
                ],
            ],
            'sources' => [[
                'type' => 'tool',
                'title' => 'Workflow Task Queues',
                'ref' => 'workflow:queue_summary',
            ]],
            'confidence' => 'high',
            'access_notes' => [
                'had_denied_request' => false,
                'reason' => '',
            ],
        ];
    }
}
