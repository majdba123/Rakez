<?php

namespace App\Services\AI\Drafts;

use App\Models\Contract;
use App\Models\SalesReservation;
use App\Models\User;
use Illuminate\Support\Arr;

class AssistantDraftSchemaFactory
{
    /**
     * @param  array<string, mixed>  $flow
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    public function build(array $flow, array $rawSlots): array
    {
        return match ($flow['key']) {
            'create_task_draft' => $this->buildTaskDraft($rawSlots),
            'create_marketing_task_draft' => $this->buildMarketingTaskDraft($rawSlots),
            'create_lead_draft' => $this->buildLeadDraft($rawSlots),
            'log_reservation_action_draft' => $this->buildReservationActionDraft($rawSlots),
            'log_credit_client_contact_draft' => $this->buildCreditClientContactDraft($rawSlots),
            default => [
                'payload' => [],
                'raw_slots' => $rawSlots,
                'warnings' => ['Unsupported flow mapping.'],
                'entity_confirmation' => $this->emptyEntityConfirmation(),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    private function buildTaskDraft(array $rawSlots): array
    {
        $entityConfirmation = $this->emptyEntityConfirmation();

        $payload = $this->filterNulls([
            'task_name' => $rawSlots['task_name'] ?? null,
            'section' => $rawSlots['section'] ?? null,
            'due_at' => $rawSlots['due_at'] ?? null,
            'team_id' => $rawSlots['team_id'] ?? null,
        ]);

        $assigneeResolution = $this->resolveUserReference(
            $rawSlots['assigned_to'] ?? null,
            $rawSlots['assigned_to_reference'] ?? null
        );
        $this->mergeEntityResolution($entityConfirmation, 'assigned_to', $assigneeResolution);

        if (($assigneeResolution['status'] ?? null) === 'resolved') {
            $payload['assigned_to'] = $assigneeResolution['entity']['id'];
            $payload['section'] = $payload['section'] ?? $assigneeResolution['entity']['type'];
            $payload['team_id'] = $payload['team_id'] ?? $assigneeResolution['entity']['team_id'];
        }

        return [
            'payload' => $this->filterNulls($payload),
            'raw_slots' => $rawSlots,
            'warnings' => [],
            'entity_confirmation' => $entityConfirmation,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    private function buildMarketingTaskDraft(array $rawSlots): array
    {
        $entityConfirmation = $this->emptyEntityConfirmation();

        $payload = $this->filterNulls([
            'task_name' => $rawSlots['task_name'] ?? null,
            'marketing_project_id' => $rawSlots['marketing_project_id'] ?? null,
            'participating_marketers_count' => $rawSlots['participating_marketers_count'] ?? null,
            'design_link' => $rawSlots['design_link'] ?? null,
            'design_number' => $rawSlots['design_number'] ?? null,
            'design_description' => $rawSlots['design_description'] ?? null,
            'status' => $rawSlots['status'] ?? null,
        ]);

        $contractResolution = $this->resolveContractReference(
            $rawSlots['contract_id'] ?? null,
            $rawSlots['contract_reference'] ?? null
        );
        $this->mergeEntityResolution($entityConfirmation, 'contract_id', $contractResolution);
        if (($contractResolution['status'] ?? null) === 'resolved') {
            $payload['contract_id'] = $contractResolution['entity']['id'];
        }

        $marketerResolution = $this->resolveUserReference(
            $rawSlots['marketer_id'] ?? null,
            $rawSlots['marketer_reference'] ?? null,
            ['type' => 'marketing']
        );
        $this->mergeEntityResolution($entityConfirmation, 'marketer_id', $marketerResolution);
        if (($marketerResolution['status'] ?? null) === 'resolved') {
            $payload['marketer_id'] = $marketerResolution['entity']['id'];
        }

        return [
            'payload' => $this->filterNulls($payload),
            'raw_slots' => $rawSlots,
            'warnings' => [],
            'entity_confirmation' => $entityConfirmation,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    private function buildLeadDraft(array $rawSlots): array
    {
        $entityConfirmation = $this->emptyEntityConfirmation();
        $warnings = [];

        $payload = $this->filterNulls([
            'name' => $rawSlots['lead_name'] ?? null,
            'contact_info' => $rawSlots['lead_contact_info'] ?? null,
            'source' => $rawSlots['lead_source'] ?? null,
            'status' => $rawSlots['lead_status'] ?? null,
        ]);

        $projectResolution = $this->resolveContractReference(
            $rawSlots['project_id'] ?? null,
            $rawSlots['project_reference'] ?? null
        );
        $this->mergeEntityResolution($entityConfirmation, 'project_id', $projectResolution);
        if (($projectResolution['status'] ?? null) === 'resolved') {
            $payload['project_id'] = $projectResolution['entity']['id'];
        }

        $assigneeResolution = $this->resolveUserReference(
            $rawSlots['assigned_to'] ?? null,
            $rawSlots['assigned_to_reference'] ?? null,
            ['type' => 'marketing']
        );
        if (
            ($rawSlots['assigned_to'] ?? null) !== null
            || (is_string($rawSlots['assigned_to_reference'] ?? null) && trim((string) $rawSlots['assigned_to_reference']) !== '')
        ) {
            $this->mergeEntityResolution($entityConfirmation, 'assigned_to', $assigneeResolution);
        }
        if (($assigneeResolution['status'] ?? null) === 'resolved') {
            $payload['assigned_to'] = $assigneeResolution['entity']['id'];
        }

        if (is_string($rawSlots['lead_notes'] ?? null) && trim($rawSlots['lead_notes']) !== '') {
            $warnings[] = 'Lead notes are excluded from the draft payload because the current lead write surface does not safely persist them.';
        }

        return [
            'payload' => $this->filterNulls($payload),
            'raw_slots' => $rawSlots,
            'warnings' => $warnings,
            'entity_confirmation' => $entityConfirmation,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    private function buildReservationActionDraft(array $rawSlots): array
    {
        $entityConfirmation = $this->emptyEntityConfirmation();

        $payload = $this->filterNulls([
            'action_type' => $this->normalizeReservationActionType($rawSlots['action_type'] ?? null),
            'notes' => $rawSlots['notes'] ?? null,
        ]);

        $reservationResolution = $this->resolveReservationReference(
            $rawSlots['sales_reservation_id'] ?? null,
            $rawSlots['reservation_reference'] ?? null
        );
        $this->mergeEntityResolution($entityConfirmation, 'sales_reservation_id', $reservationResolution);
        if (($reservationResolution['status'] ?? null) === 'resolved') {
            $payload['sales_reservation_id'] = $reservationResolution['entity']['id'];
        }

        return [
            'payload' => $this->filterNulls($payload),
            'raw_slots' => $rawSlots,
            'warnings' => [],
            'entity_confirmation' => $entityConfirmation,
        ];
    }

    /**
     * @param  array<string, mixed>  $rawSlots
     * @return array<string, mixed>
     */
    private function buildCreditClientContactDraft(array $rawSlots): array
    {
        $entityConfirmation = $this->emptyEntityConfirmation();

        $payload = $this->filterNulls([
            'notes' => $rawSlots['notes'] ?? null,
        ]);

        $reservationResolution = $this->resolveReservationReference(
            $rawSlots['sales_reservation_id'] ?? null,
            $rawSlots['reservation_reference'] ?? null
        );
        $this->mergeEntityResolution($entityConfirmation, 'sales_reservation_id', $reservationResolution);
        if (($reservationResolution['status'] ?? null) === 'resolved') {
            $payload['sales_reservation_id'] = $reservationResolution['entity']['id'];
        }

        return [
            'payload' => $this->filterNulls($payload),
            'raw_slots' => $rawSlots,
            'warnings' => [],
            'entity_confirmation' => $entityConfirmation,
        ];
    }

    /**
     * @param  int|string|null  $id
     * @param  string|null  $reference
     * @param  array<string, mixed>  $extraFilters
     * @return array<string, mixed>
     */
    private function resolveUserReference($id, ?string $reference, array $extraFilters = []): array
    {
        $query = User::query()->where('is_active', true);

        foreach ($extraFilters as $column => $value) {
            $query->where($column, $value);
        }

        if ($id !== null) {
            $user = (clone $query)->find((int) $id);

            return $user
                ? ['status' => 'resolved', 'entity' => $this->userEntity($user)]
                : ['status' => 'unresolved', 'message' => 'Referenced user was not found.'];
        }

        if (! is_string($reference) || trim($reference) === '') {
            return ['status' => 'unresolved', 'message' => 'User reference is required.'];
        }

        $normalized = trim($reference);
        $matches = (clone $query)
            ->where(function ($builder) use ($normalized): void {
                $builder->where('name', $normalized)
                    ->orWhere('email', $normalized);
            })
            ->get(['id', 'name', 'email', 'type', 'team_id']);

        if ($matches->count() === 1) {
            return ['status' => 'resolved', 'entity' => $this->userEntity($matches->first())];
        }

        if ($matches->count() > 1) {
            return [
                'status' => 'ambiguous',
                'reference' => $normalized,
                'candidates' => $matches->map(fn (User $user) => $this->userEntity($user))->all(),
                'message' => 'User reference matched multiple active users.',
            ];
        }

        return ['status' => 'unresolved', 'reference' => $normalized, 'message' => 'User reference could not be resolved exactly.'];
    }

    /**
     * @param  int|string|null  $id
     * @param  string|null  $reference
     * @return array<string, mixed>
     */
    private function resolveContractReference($id, ?string $reference): array
    {
        if ($id !== null) {
            $contract = Contract::query()->find((int) $id);

            return $contract
                ? ['status' => 'resolved', 'entity' => $this->contractEntity($contract)]
                : ['status' => 'unresolved', 'message' => 'Referenced project was not found.'];
        }

        if (! is_string($reference) || trim($reference) === '') {
            return ['status' => 'unresolved', 'message' => 'Project reference is required.'];
        }

        $normalized = trim($reference);
        $matches = Contract::query()
            ->where('project_name', $normalized)
            ->get(['id', 'project_name']);

        if ($matches->count() === 1) {
            return ['status' => 'resolved', 'entity' => $this->contractEntity($matches->first())];
        }

        if ($matches->count() > 1) {
            return [
                'status' => 'ambiguous',
                'reference' => $normalized,
                'candidates' => $matches->map(fn (Contract $contract) => $this->contractEntity($contract))->all(),
                'message' => 'Project reference matched multiple projects.',
            ];
        }

        return ['status' => 'unresolved', 'reference' => $normalized, 'message' => 'Project reference could not be resolved exactly.'];
    }

    /**
     * @param  int|string|null  $id
     * @param  string|null  $reference
     * @return array<string, mixed>
     */
    private function resolveReservationReference($id, ?string $reference): array
    {
        $candidateId = $id;

        if ($candidateId === null && is_string($reference) && preg_match('/^\d+$/', trim($reference))) {
            $candidateId = (int) trim($reference);
        }

        if ($candidateId === null) {
            return [
                'status' => 'unresolved',
                'message' => 'Reservation-linked drafts require an explicit numeric reservation id.',
            ];
        }

        $reservation = SalesReservation::query()->find((int) $candidateId);

        return $reservation
            ? ['status' => 'resolved', 'entity' => ['id' => $reservation->id, 'label' => "Reservation #{$reservation->id}"]]
            : ['status' => 'unresolved', 'message' => 'Referenced reservation was not found.'];
    }

    /**
     * @param  array<string, mixed>  $entityConfirmation
     * @param  array<string, mixed>  $resolution
     */
    private function mergeEntityResolution(array &$entityConfirmation, string $field, array $resolution): void
    {
        $status = $resolution['status'] ?? 'unresolved';

        if ($status === 'resolved') {
            $entityConfirmation['confirmed'][] = [
                'field' => $field,
                'entity' => $resolution['entity'],
            ];

            return;
        }

        if ($status === 'ambiguous') {
            $entityConfirmation['ambiguous'][] = array_merge(['field' => $field], $resolution);

            return;
        }

        $entityConfirmation['unresolved'][] = array_merge(['field' => $field], $resolution);
    }

    /**
     * @return array<string, mixed>
     */
    private function userEntity(User $user): array
    {
        return [
            'id' => $user->id,
            'label' => $user->name,
            'email' => $user->email,
            'type' => $user->type,
            'team_id' => $user->team_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractEntity(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'label' => $contract->project_name,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterNulls(array $data): array
    {
        return Arr::where($data, fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeReservationActionType(?string $actionType): ?string
    {
        if ($actionType === null) {
            return null;
        }

        $map = [
            'استقطاب' => 'lead_acquisition',
            'اكتساب العملاء' => 'lead_acquisition',
            'إقناع' => 'persuasion',
            'الإقناع' => 'persuasion',
            'إغلاق' => 'closing',
            'الإغلاق' => 'closing',
            'اغلاق الصفقة' => 'closing',
        ];

        return $map[$actionType] ?? $actionType;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function emptyEntityConfirmation(): array
    {
        return [
            'confirmed' => [],
            'ambiguous' => [],
            'unresolved' => [],
        ];
    }
}
