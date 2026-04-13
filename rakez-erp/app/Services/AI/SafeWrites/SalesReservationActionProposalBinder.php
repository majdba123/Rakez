<?php

namespace App\Services\AI\SafeWrites;

/**
 * Canonical binding for sales_reservation.action.log safe-write proposals (single action).
 * action_type is normalized to the same enum as {@see \App\Http\Requests\Sales\StoreReservationActionRequest}.
 */
class SalesReservationActionProposalBinder
{
    public const ACTION_KEY = 'sales_reservation.action.log';

    public const EXPECTED_FLOW_KEY = 'log_reservation_action_draft';

    /** Same canonical set as StoreReservationActionRequest rules. */
    private const ALLOWED_ACTION_TYPES = ['lead_acquisition', 'persuasion', 'closing'];

    /**
     * Arabic labels → canonical enum (aligned with StoreReservationActionRequest::prepareForValidation).
     *
     * @var array<string, string>
     */
    private const ACTION_TYPE_ARABIC_MAP = [
        'استقطاب' => 'lead_acquisition',
        'اكتساب العملاء' => 'lead_acquisition',
        'إقناع' => 'persuasion',
        'الإقناع' => 'persuasion',
        'إغلاق' => 'closing',
        'الإغلاق' => 'closing',
        'اغلاق الصفقة' => 'closing',
    ];

    /** Upper bound for notes; TEXT column — fail closed on excessive payloads. */
    private const NOTES_MAX_CHARS = 65535;

    /**
     * Deterministic idempotency key: same canonical binding as fingerprint. Null when shape invalid.
     *
     * @param  array<string, mixed>  $proposal
     */
    public function deriveStableIdempotencyKey(array $proposal): ?string
    {
        if ($this->validateBindingShape($proposal) !== null) {
            return null;
        }

        $canonical = json_encode($this->bindingPayload($proposal), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'sw_sra:'.hash('sha256', (string) $canonical);
    }

    /**
     * Deterministic fingerprint over binding-critical proposal fields.
     *
     * @param  array<string, mixed>  $proposal
     */
    public function fingerprint(array $proposal): string
    {
        $payload = hash('sha256', json_encode($this->bindingPayload($proposal), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    /**
     * Canonical binding: includes reservation id, canonical action_type, trimmed notes.
     *
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    public function bindingPayload(array $proposal): array
    {
        $draftPayload = (array) (($proposal['draft'] ?? [])['payload'] ?? []);
        $flow = (array) ($proposal['flow'] ?? []);

        $canonicalType = $this->normalizeActionTypeToCanonical((string) ($draftPayload['action_type'] ?? ''));

        return [
            'flow_key' => (string) ($flow['key'] ?? ''),
            'handoff_path' => (string) (($flow['handoff'] ?? [])['path'] ?? ''),
            'sales_reservation_id' => (int) ($draftPayload['sales_reservation_id'] ?? 0),
            'action_type' => $canonicalType ?? '',
            'notes' => $this->normalizeNotes((string) ($draftPayload['notes'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    public function validateBindingShape(array $proposal): ?string
    {
        $flowKey = (string) (($proposal['flow'] ?? [])['key'] ?? '');
        if ($flowKey !== self::EXPECTED_FLOW_KEY) {
            return 'proposal_binding.invalid_flow_key';
        }

        $id = (int) (($proposal['draft']['payload']['sales_reservation_id'] ?? 0));
        if ($id < 1) {
            return 'proposal_binding.missing_sales_reservation_id';
        }

        $path = (string) (($proposal['flow']['handoff']['path'] ?? ''));
        if ($path === '' || ! preg_match('#/api/sales/reservations/\d+/actions#', $path)) {
            return 'proposal_binding.invalid_handoff_path';
        }

        $pathReservationId = $this->parseReservationIdFromHandoffPath($path);
        if ($pathReservationId === null) {
            return 'proposal_binding.invalid_handoff_path';
        }

        if ($pathReservationId !== $id) {
            return 'proposal_binding.handoff_path_reservation_mismatch';
        }

        $rawActionType = (string) (($proposal['draft']['payload']['action_type'] ?? ''));
        if ($rawActionType === '') {
            return 'proposal_binding.missing_action_type';
        }

        if ($this->normalizeActionTypeToCanonical($rawActionType) === null) {
            return 'proposal_binding.invalid_action_type';
        }

        $notes = (string) (($proposal['draft']['payload']['notes'] ?? ''));
        if (mb_strlen($notes) > self::NOTES_MAX_CHARS) {
            return 'proposal_binding.notes_too_long';
        }

        return null;
    }

    /**
     * @return non-empty-string|null  Canonical enum or null if not allowed after map + validation
     */
    private function normalizeActionTypeToCanonical(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (isset(self::ACTION_TYPE_ARABIC_MAP[$trimmed])) {
            $trimmed = self::ACTION_TYPE_ARABIC_MAP[$trimmed];
        }

        if (! in_array($trimmed, self::ALLOWED_ACTION_TYPES, true)) {
            return null;
        }

        return $trimmed;
    }

    private function normalizeNotes(string $notes): string
    {
        return trim($notes);
    }

    /**
     * Notes for domain write (trimmed); null when empty — aligned with HTTP nullable notes.
     *
     * @param  array<string, mixed>  $proposal
     */
    public function extractNotes(array $proposal): ?string
    {
        $n = $this->normalizeNotes((string) (($proposal['draft']['payload']['notes'] ?? '')));

        return $n === '' ? null : $n;
    }

    /**
     * Reservation id in /api/sales/reservations/{id}/actions (action-specific).
     */
    private function parseReservationIdFromHandoffPath(string $path): ?int
    {
        if (! preg_match('#/api/sales/reservations/(\d+)/actions#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
