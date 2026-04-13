<?php

namespace App\Services\AI\SafeWrites;

/**
 * Canonical binding for credit client contact safe-write proposals (single action).
 * Prevents confirm-time drift from earlier proposal/preview snapshots.
 */
class CreditClientContactProposalBinder
{
    public const ACTION_KEY = 'credit_booking.client_contact.log';

    public const EXPECTED_FLOW_KEY = 'log_credit_client_contact_draft';

    /**
     * Deterministic idempotency key for this action only: derived from the same canonical binding as the fingerprint.
     * Returns null when the proposal cannot participate in a binding contract (fail closed upstream).
     *
     * @param  array<string, mixed>  $proposal
     */
    public function deriveStableIdempotencyKey(array $proposal): ?string
    {
        if ($this->validateBindingShape($proposal) !== null) {
            return null;
        }

        $canonical = json_encode($this->bindingPayload($proposal), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return 'sw_cc:'.hash('sha256', (string) $canonical);
    }

    /**
     * Deterministic fingerprint over binding-critical proposal fields (no PII in hash inputs beyond notes).
     *
     * @param  array<string, mixed>  $proposal
     */
    public function fingerprint(array $proposal): string
    {
        $payload = hash('sha256', json_encode($this->bindingPayload($proposal), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $proposal
     * @return array<string, mixed>
     */
    public function bindingPayload(array $proposal): array
    {
        $draftPayload = (array) (($proposal['draft'] ?? [])['payload'] ?? []);
        $flow = (array) ($proposal['flow'] ?? []);

        return [
            'flow_key' => (string) ($flow['key'] ?? ''),
            'handoff_path' => (string) (($flow['handoff'] ?? [])['path'] ?? ''),
            'sales_reservation_id' => (int) ($draftPayload['sales_reservation_id'] ?? 0),
            'notes' => (string) ($draftPayload['notes'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $proposal
     */
    public function extractNotes(array $proposal): ?string
    {
        $notes = $this->bindingPayload($proposal)['notes'] ?? '';

        return $notes === '' ? null : $notes;
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
        if ($path === '' || ! preg_match('#/credit/bookings/\d+/actions#', $path)) {
            return 'proposal_binding.invalid_handoff_path';
        }

        $pathReservationId = $this->parseReservationIdFromHandoffPath($path);
        if ($pathReservationId === null) {
            return 'proposal_binding.invalid_handoff_path';
        }

        if ($pathReservationId !== $id) {
            return 'proposal_binding.handoff_path_reservation_mismatch';
        }

        $notes = (string) (($proposal['draft']['payload']['notes'] ?? ''));
        if (mb_strlen($notes) > 2000) {
            return 'proposal_binding.notes_too_long';
        }

        return null;
    }

    /**
     * Reservation id embedded in the credit booking handoff path (action-specific; not a general URL parser).
     */
    private function parseReservationIdFromHandoffPath(string $path): ?int
    {
        if (! preg_match('#/credit/bookings/(\d+)/actions#', $path, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
