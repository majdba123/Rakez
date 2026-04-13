<?php

namespace App\Services\AI\Drafts;

use App\Http\Requests\Marketing\StoreMarketingTaskRequest;
use App\Http\Requests\Sales\StoreReservationActionRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Models\User;

class AssistantDraftFlowRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        return array_values(array_filter(
            $this->definitions(),
            fn (array $definition) => $user->can($definition['permission'])
        ));
    }

    /**
     * @return array<int, string>
     */
    public function supportedKeysForUser(User $user): array
    {
        return array_values(array_map(
            fn (array $definition) => $definition['key'],
            $this->listForUser($user)
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function blockedFlows(): array
    {
        return [
            [
                'key' => 'contract_write',
                'label' => 'Contract create or modify',
                'reason' => 'Contracts remain outside v1 draft assistance because they are higher-risk operational writes.',
            ],
            [
                'key' => 'money_write',
                'label' => 'Payment, deposit, or commission writes',
                'reason' => 'Money-related operations are explicitly forbidden in v1 draft assistance.',
            ],
            [
                'key' => 'status_change',
                'label' => 'Project or contract status changes',
                'reason' => 'Status transitions can trigger downstream workflow effects and are blocked in v1.',
            ],
            [
                'key' => 'approval_action',
                'label' => 'Approval or rejection actions',
                'reason' => 'Approval decisions stay outside assistant-prepared drafts.',
            ],
            [
                'key' => 'bulk_action',
                'label' => 'Bulk operations',
                'reason' => 'Bulk actions are intentionally unsupported to avoid accidental wide impact.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            [
                'key' => 'create_task_draft',
                'label' => 'Create Task Draft',
                'permission' => 'use-ai-assistant',
                'description' => 'Prepare a draft for the generic task creation endpoint without submitting it.',
                'safe_write_action_key' => 'task.create',
                'required_fields' => ['task_name', 'assigned_to', 'due_at'],
                'optional_fields' => ['section', 'team_id'],
                'validation_request' => StoreTaskRequest::class,
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/tasks',
                ],
                'entity_rules' => [
                    'assigned_to' => 'Resolve only exact unique active user matches by id, email, or exact name. Infer section and team from assignee when available.',
                ],
                'ambiguity_rules' => [
                    'If the assignee matches multiple users, do not prepare a ready draft.',
                    'If due date/time is missing or incomplete, return needs_input instead of guessing.',
                ],
                'refusal_cases' => [
                    'Auto-submit requests',
                    'Bulk task creation',
                    'Requests to update or close existing tasks',
                ],
            ],
            [
                'key' => 'create_marketing_task_draft',
                'label' => 'Create Marketing Task Draft',
                'permission' => 'marketing.tasks.confirm',
                'description' => 'Prepare a draft for marketing task creation for human review only.',
                'safe_write_action_key' => 'marketing_task.create',
                'required_fields' => ['contract_id', 'task_name', 'marketer_id'],
                'optional_fields' => [
                    'marketing_project_id',
                    'participating_marketers_count',
                    'design_link',
                    'design_number',
                    'design_description',
                    'status',
                ],
                'validation_request' => StoreMarketingTaskRequest::class,
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/marketing/tasks',
                ],
                'entity_rules' => [
                    'contract_id' => 'Resolve from explicit id or exact unique project name only.',
                    'marketer_id' => 'Resolve only exact unique active marketing user matches.',
                ],
                'ambiguity_rules' => [
                    'If project reference is ambiguous, require manual selection.',
                    'If marketer reference is ambiguous or inactive, do not mark draft ready.',
                ],
                'refusal_cases' => [
                    'Any request to change marketing task status for an existing task',
                    'Any request to assign multiple marketers in bulk',
                ],
            ],
            [
                'key' => 'create_lead_draft',
                'label' => 'Create Lead Draft',
                'permission' => 'marketing.projects.view',
                'description' => 'Prepare a lead creation draft for human review only without submitting it.',
                'safe_write_action_key' => 'lead.create',
                'required_fields' => ['name', 'contact_info', 'project_id'],
                'optional_fields' => ['assigned_to', 'source', 'status'],
                'validation_request' => \App\Http\Requests\Marketing\StoreLeadRequest::class,
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/marketing/leads',
                ],
                'entity_rules' => [
                    'project_id' => 'Resolve from explicit id or exact unique project name only.',
                    'assigned_to' => 'Resolve only exact unique active marketing user matches.',
                ],
                'ambiguity_rules' => [
                    'If project reference is ambiguous, require manual selection.',
                    'If assignee reference is ambiguous, do not mark draft ready.',
                    'Do not infer lead notes into the handoff payload because the current lead write surface does not safely persist them.',
                ],
                'refusal_cases' => [
                    'Auto-submit requests',
                    'Bulk lead imports',
                    'Lead assignment/status changes for existing leads',
                ],
            ],
            [
                'key' => 'log_reservation_action_draft',
                'label' => 'Log Reservation Action Draft',
                'permission' => 'sales.reservations.view',
                'description' => 'Prepare a reservation follow-up action draft without writing it.',
                'safe_write_action_key' => 'sales_reservation.action.log',
                'required_fields' => ['sales_reservation_id', 'action_type'],
                'optional_fields' => ['notes'],
                'validation_request' => StoreReservationActionRequest::class,
                'handoff' => [
                    'method' => 'POST',
                    'path_template' => '/api/sales/reservations/{sales_reservation_id}/actions',
                ],
                'entity_rules' => [
                    'sales_reservation_id' => 'Require an explicit numeric reservation id. Do not guess from customer names or fuzzy references.',
                ],
                'ambiguity_rules' => [
                    'If reservation id is absent, request it explicitly.',
                ],
                'refusal_cases' => [
                    'Reservation create, confirm, or cancel requests',
                    'Payment intent or deposit logging requests',
                ],
            ],
            [
                'key' => 'log_credit_client_contact_draft',
                'label' => 'Log Credit Client Contact Draft',
                'permission' => 'credit.bookings.manage',
                'description' => 'Prepare a low-risk append-only credit follow-up note draft without writing it.',
                'safe_write_action_key' => 'credit_booking.client_contact.log',
                'required_fields' => ['sales_reservation_id'],
                'optional_fields' => ['notes'],
                'validation_request' => \App\Http\Requests\Credit\StoreCreditClientContactRequest::class,
                'handoff' => [
                    'method' => 'POST',
                    'path_template' => '/api/credit/bookings/{sales_reservation_id}/actions',
                ],
                'entity_rules' => [
                    'sales_reservation_id' => 'Require an explicit numeric reservation id. Do not infer from client names or fuzzy references.',
                ],
                'ambiguity_rules' => [
                    'If reservation id is absent, request it explicitly.',
                    'Treat this as append-only note preparation only. Do not modify booking fields.',
                ],
                'refusal_cases' => [
                    'Credit-side approval or rejection requests',
                    'Any request to edit confirmed booking financial details',
                ],
            ],
        ];
    }
}
