<?php

namespace App\Services\AI\SafeWrites;

use App\Services\AI\SafeWrites\Handlers\DraftBackedSafeWriteActionHandler;
use App\Services\AI\SafeWrites\Handlers\MetadataOnlySafeWriteActionHandler;
use Illuminate\Support\Arr;

class SafeWriteActionRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return [
            [
                'key' => 'task.create',
                'label' => 'Create Task',
                'classification' => 'safe_for_v1_draft_only',
                'permission' => 'use-ai-assistant',
                'handler' => DraftBackedSafeWriteActionHandler::class,
                'draft_flow_key' => 'create_task_draft',
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/tasks'],
                'reusable_surface' => 'App\Http\Controllers\MyTasksController::store',
                'strict_input_schema' => [
                    'message' => 'required|string|max:4000',
                ],
                'entity_resolution' => [
                    'assigned_to' => 'Resolve only exact unique active user matches by id, email, or exact name.',
                ],
                'ambiguity_detection' => [
                    'Do not mark ready when the assignee matches multiple users.',
                    'Do not guess due date or time when missing.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:task.create:{user_id}:{sha256(normalized_input)}',
                'audit_log_requirements' => [
                    'Record propose, preview, confirm attempt, and reject events in ai_audit_trail.',
                    'Store action key, classification, idempotency key summary, and handoff endpoint.',
                ],
                'rollback_behavior' => 'No business write occurs in current mode. Future activation must wrap execution in a transaction and record before/after audit.',
                'activation_state' => 'draft_only',
                'activation_message' => 'Draft-only flow available. Direct execution is intentionally disabled.',
            ],
            [
                'key' => 'marketing_task.create',
                'label' => 'Create Marketing Task',
                'classification' => 'safe_for_v1_draft_only',
                'permission' => 'marketing.tasks.confirm',
                'handler' => DraftBackedSafeWriteActionHandler::class,
                'draft_flow_key' => 'create_marketing_task_draft',
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/marketing/tasks'],
                'reusable_surface' => 'App\Services\Marketing\MarketingTaskService::createTask',
                'strict_input_schema' => [
                    'message' => 'required|string|max:4000',
                ],
                'entity_resolution' => [
                    'contract_id' => 'Resolve from explicit id or exact unique project name only.',
                    'marketer_id' => 'Resolve only exact unique active marketing user matches.',
                ],
                'ambiguity_detection' => [
                    'Do not mark ready when the project reference is ambiguous.',
                    'Do not mark ready when marketer reference is ambiguous or inactive.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:marketing_task.create:{user_id}:{sha256(normalized_input)}',
                'audit_log_requirements' => [
                    'Record propose, preview, confirm attempt, and reject events in ai_audit_trail.',
                    'Include task assignee resolution status and notification side-effect risk.',
                ],
                'rollback_behavior' => 'Current mode performs no write. Future activation must isolate task creation from notification dispatch failure.',
                'activation_state' => 'draft_only',
                'activation_message' => 'Draft-only flow available. Direct execution is intentionally disabled.',
            ],
            [
                'key' => 'sales_reservation.action.log',
                'label' => 'Log Reservation Action',
                'classification' => 'safe_for_v1_draft_only',
                'permission' => 'sales.reservations.view',
                'handler' => DraftBackedSafeWriteActionHandler::class,
                'draft_flow_key' => 'log_reservation_action_draft',
                'normal_endpoint' => ['method' => 'POST', 'path_template' => '/api/sales/reservations/{sales_reservation_id}/actions'],
                'reusable_surface' => 'App\Services\Sales\SalesReservationService::logAction',
                'strict_input_schema' => [
                    'message' => 'required|string|max:4000',
                ],
                'entity_resolution' => [
                    'sales_reservation_id' => 'Require explicit numeric reservation id. Do not infer from customer names.',
                ],
                'ambiguity_detection' => [
                    'If reservation id is missing, return needs_input.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:sales_reservation.action.log:{user_id}:{reservation_id}:{sha256(normalized_notes)}',
                'audit_log_requirements' => [
                    'Record propose, preview, confirm attempt, and reject events in ai_audit_trail.',
                    'Persist reservation id and action type in audit summaries.',
                ],
                'rollback_behavior' => 'Current mode performs no write. Future activation must treat duplicate action logs as idempotent by reservation id + normalized note hash.',
                'activation_state' => 'draft_only',
                'activation_message' => 'Draft-only flow available. Direct execution is intentionally disabled.',
                'intent_execution_candidate' => true,
            ],
            [
                'key' => 'lead.create',
                'label' => 'Create Lead',
                'classification' => 'safe_for_v1_draft_only',
                'permission' => 'marketing.projects.view',
                'handler' => DraftBackedSafeWriteActionHandler::class,
                'draft_flow_key' => 'create_lead_draft',
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/marketing/leads'],
                'reusable_surface' => 'App\Http\Controllers\Marketing\LeadController::store',
                'strict_input_schema' => [
                    'message' => 'required|string|max:4000',
                ],
                'entity_resolution' => [
                    'project_id' => 'Resolve from explicit id or exact unique project name only.',
                    'assigned_to' => 'Resolve only exact unique active marketing user matches.',
                ],
                'ambiguity_detection' => [
                    'Do not mark ready when project reference is ambiguous.',
                    'Do not mark ready when assignee reference is ambiguous.',
                    'Do not auto-carry lead notes into the handoff payload because the current write surface does not safely persist them.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:lead.create:{user_id}:{sha256(normalized_input)}',
                'audit_log_requirements' => [
                    'Record lead draft propose, preview, confirm attempt, and reject events in ai_audit_trail.',
                    'Redact contact_info and summary-only lead references in audit payloads.',
                ],
                'rollback_behavior' => 'Current mode performs no write. Future activation must address lead note persistence explicitly before any direct execution path is considered.',
                'activation_state' => 'draft_only',
                'activation_message' => 'Lead drafting is available in draft-only mode. Direct execution is intentionally disabled.',
            ],
            [
                'key' => 'credit_booking.client_contact.log',
                'label' => 'Log Credit Client Contact',
                'classification' => 'safe_for_v1_draft_only',
                'permission' => 'credit.bookings.manage',
                'handler' => DraftBackedSafeWriteActionHandler::class,
                'draft_flow_key' => 'log_credit_client_contact_draft',
                'normal_endpoint' => ['method' => 'POST', 'path_template' => '/api/credit/bookings/{sales_reservation_id}/actions'],
                'reusable_surface' => 'App\Services\Sales\SalesReservationService::logCreditClientContact',
                'strict_input_schema' => [
                    'message' => 'required|string|max:4000',
                ],
                'entity_resolution' => [
                    'sales_reservation_id' => 'Require explicit numeric reservation id. Do not infer from client names.',
                ],
                'ambiguity_detection' => [
                    'If reservation id is missing, return needs_input.',
                    'Treat this as append-only follow-up note preparation only.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:credit_booking.client_contact.log:{user_id}:{reservation_id}:{sha256(normalized_notes)}',
                'audit_log_requirements' => [
                    'Record propose, preview, confirm attempt, and reject events in ai_audit_trail.',
                    'Persist reservation id presence and note-only scope in audit summaries.',
                ],
                'rollback_behavior' => 'Current mode performs no write. Future activation must recheck booking existence and keep the operation append-only.',
                'activation_state' => 'draft_only',
                'activation_message' => 'Credit follow-up drafting is available in draft-only mode. Direct execution is intentionally disabled.',
                'intent_execution_candidate' => true,
            ],
            [
                'key' => 'waiting_list.create',
                'label' => 'Create Waiting List Entry',
                'classification' => 'safe_only_with_confirmation',
                'permission' => 'sales.waiting_list.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/sales/waiting-list'],
                'reusable_surface' => 'App\Services\Sales\WaitingListService::createWaitingListEntry',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'contract_id' => 'Explicit numeric id only.',
                    'contract_unit_id' => 'Explicit numeric id only.',
                ],
                'ambiguity_detection' => [
                    'Client identity and unit availability must be confirmed before any write.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:waiting_list.create:{user_id}:{contract_unit_id}:{sha256(client_identity)}',
                'audit_log_requirements' => [
                    'Audit must capture client PII in redacted form and unit availability snapshot.',
                ],
                'rollback_behavior' => 'Future activation requires transaction + duplicate client guard + availability recheck immediately before commit.',
                'activation_state' => 'confirmation_only_disabled',
                'activation_message' => 'This action is not enabled for assistant execution. Future activation requires explicit confirmation plus stronger duplicate/availability controls.',
            ],
            [
                'key' => 'exclusive_project.request.create',
                'label' => 'Create Exclusive Project Request',
                'classification' => 'safe_only_with_confirmation',
                'permission' => 'use-ai-assistant',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/exclusive-projects'],
                'reusable_surface' => 'App\Services\ExclusiveProjectService::createRequest',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'location_city' => 'Resolve from exact supported city name.',
                    'location_district' => 'Resolve within selected city only.',
                ],
                'ambiguity_detection' => [
                    'Unit mix and developer identity must be confirmed before any write.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:exclusive_project.request.create:{user_id}:{sha256(project_name+developer_name+location_city)}',
                'audit_log_requirements' => [
                    'Audit should store requester, estimated total value, and approval-required workflow stage.',
                ],
                'rollback_behavior' => 'Future activation requires transaction, duplicate request detection, and approval workflow linkage.',
                'activation_state' => 'confirmation_only_disabled',
                'activation_message' => 'This action is not enabled for assistant execution. Future activation requires explicit confirmation and duplicate request controls.',
            ],
            [
                'key' => 'sales_target.create',
                'label' => 'Create Sales Target',
                'classification' => 'safe_only_with_confirmation',
                'permission' => 'sales.targets.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/sales/targets'],
                'reusable_surface' => 'App\Services\Sales\SalesTargetService::createTarget',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'contract_id' => 'Explicit project id only.',
                    'marketer_id' => 'Exact unique teammate id only.',
                    'contract_unit_ids' => 'Explicit unit ids only.',
                ],
                'ambiguity_detection' => [
                    'Do not infer team membership or unit scope.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'safe-write:sales_target.create:{user_id}:{contract_id}:{marketer_id}:{sha256(unit_ids+dates)}',
                'audit_log_requirements' => [
                    'Audit must capture team scope check and selected unit set.',
                ],
                'rollback_behavior' => 'Future activation requires atomic target + pivot sync with pre-commit team/project authorization recheck.',
                'activation_state' => 'confirmation_only_disabled',
                'activation_message' => 'This action is not enabled for assistant execution. Future activation requires team-scope confirmation and revalidation at commit time.',
            ],
            [
                'key' => 'contract.create',
                'label' => 'Create Contract',
                'classification' => 'forbidden_entirely',
                'permission' => 'contracts.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/contracts/store'],
                'reusable_surface' => 'App\Services\Contract\ContractService::create',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'Multiple linked entities across developer, project metadata, and downstream departments.',
                ],
                'ambiguity_detection' => [
                    'No assistant inference is acceptable for contract creation.',
                ],
                'dry_run_supported' => false,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'Do not enable in assistant channel.',
                'audit_log_requirements' => [
                    'Refusal attempts must still be audited.',
                ],
                'rollback_behavior' => 'Forbidden.',
                'activation_state' => 'forbidden',
                'activation_message' => 'Contract creation is forbidden for assistant execution.',
            ],
            [
                'key' => 'reservation.create',
                'label' => 'Create Sales Reservation',
                'classification' => 'not_safe_for_assistant',
                'permission' => 'sales.reservations.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/sales/reservations'],
                'reusable_surface' => 'App\Services\Sales\SalesReservationService::createReservation',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'Reservation entity matching spans project, unit, client, negotiation, and payment method.',
                ],
                'ambiguity_detection' => [
                    'No fuzzy project/unit/client matching is acceptable.',
                ],
                'dry_run_supported' => true,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'Would require reservation-scoped idempotency plus unit availability locking.',
                'audit_log_requirements' => [
                    'Would require before/after audit and notification traceability.',
                ],
                'rollback_behavior' => 'Activation blocked until unit locking, idempotency, and duplicate reservation controls exist.',
                'activation_state' => 'not_safe',
                'activation_message' => 'Sales reservation creation is not safe for assistant execution in the current system.',
            ],
            [
                'key' => 'deposit.create',
                'label' => 'Create Deposit',
                'classification' => 'forbidden_entirely',
                'permission' => 'deposits.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/deposits'],
                'reusable_surface' => 'App\Services\Sales\DepositService::createDeposit',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'Financial entities and payment evidence must be explicit and externally verified.',
                ],
                'ambiguity_detection' => [
                    'No assistant inference is acceptable for financial writes.',
                ],
                'dry_run_supported' => false,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'Do not enable in assistant channel.',
                'audit_log_requirements' => [
                    'Refusal attempts must still be audited.',
                ],
                'rollback_behavior' => 'Forbidden.',
                'activation_state' => 'forbidden',
                'activation_message' => 'Financial writes are forbidden for assistant execution.',
            ],
            [
                'key' => 'commission.create',
                'label' => 'Create Commission',
                'classification' => 'forbidden_entirely',
                'permission' => 'commissions.create',
                'handler' => MetadataOnlySafeWriteActionHandler::class,
                'normal_endpoint' => ['method' => 'POST', 'path' => '/api/commissions'],
                'reusable_surface' => 'App\Http\Controllers\Api\CommissionController::store',
                'strict_input_schema' => [
                    'payload' => 'required|array',
                ],
                'entity_resolution' => [
                    'Commission source and beneficiary must be explicitly selected by a human.',
                ],
                'ambiguity_detection' => [
                    'No assistant inference is acceptable for commission writes.',
                ],
                'dry_run_supported' => false,
                'confirmation_required' => true,
                'idempotency_key_strategy' => 'Do not enable in assistant channel.',
                'audit_log_requirements' => [
                    'Refusal attempts must still be audited.',
                ],
                'rollback_behavior' => 'Forbidden.',
                'activation_state' => 'forbidden',
                'activation_message' => 'Commission writes are forbidden for assistant execution.',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->all() as $action) {
            if ($action['key'] === $key) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogForUser($user): array
    {
        return array_values(array_map(function (array $action): array {
            return Arr::except($action, ['handler', 'draft_flow_key', 'intent_execution_candidate']);
        }, array_filter($this->all(), function (array $action) use ($user): bool {
            if (($action['permission'] ?? null) === 'use-ai-assistant') {
                return true;
            }

            return $user?->can($action['permission']) ?? false;
        })));
    }
}
