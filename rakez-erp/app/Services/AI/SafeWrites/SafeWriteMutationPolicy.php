<?php

namespace App\Services\AI\SafeWrites;

use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\CapabilityResolver;
use App\Services\AI\SectionRegistry;
use App\Services\AI\Skills\SkillRegistry;

/**
 * Record-aware mutation guard for future safe-write execution (submit path).
 * Does not perform writes. Separate from SafeWriteIntentGate (global eligibility).
 */
class SafeWriteMutationPolicy
{
    private const CREDIT_ACTION_KEY = 'credit_booking.client_contact.log';

    private const SALES_RESERVATION_ACTION_LOG_KEY = 'sales_reservation.action.log';

    private const PAIRED_SKILL_KEY = 'credit.client_contact_draft';

    public function __construct(
        private readonly SkillRegistry $skillRegistry,
        private readonly SectionRegistry $sectionRegistry,
        private readonly CapabilityResolver $capabilityResolver,
        private readonly SalesReservationActionProposalBinder $salesReservationActionBinder,
    ) {}

    /**
     * @param  array<string, mixed>  $input  Safe-write controller payload (includes proposal on confirm)
     */
    public function evaluate(User $user, string $actionKey, array $input): SafeWriteMutationPolicyDecision
    {
        return match ($actionKey) {
            self::CREDIT_ACTION_KEY => $this->evaluateCreditBookingClientContactLog($user, $input),
            self::SALES_RESERVATION_ACTION_LOG_KEY => $this->evaluateSalesReservationActionLog($user, $input),
            default => $this->deny('mutation_policy.unsupported_action', ['action_key' => $actionKey]),
        };
    }

    /**
     * Record-aware guard for sales_reservation.action.log (append-only reservation funnel log).
     * Authoritative rule: {@see \App\Policies\SalesReservationPolicy::logAction} via Gate — owner or admin only.
     * This is stricter than {@see \App\Services\Sales\SalesReservationService::logAction}, which also allows
     * anyone with sales.reservations.view; safe-write must not widen beyond the policy.
     *
     * @param  array<string, mixed>  $input
     */
    private function evaluateSalesReservationActionLog(User $user, array $input): SafeWriteMutationPolicyDecision
    {
        $proposal = (array) ($input['proposal'] ?? []);

        $bindingReason = $this->salesReservationActionBinder->validateBindingShape($proposal);
        if ($bindingReason !== null) {
            return $this->deny('mutation_policy.proposal_binding_failed', [
                'binding_reason' => $bindingReason,
            ]);
        }

        $reservationId = (int) $this->salesReservationActionBinder->bindingPayload($proposal)['sales_reservation_id'];

        $reservation = SalesReservation::query()->find($reservationId);
        if ($reservation === null) {
            return $this->deny('mutation_policy.reservation_not_found', ['sales_reservation_id' => $reservationId]);
        }

        if (! $user->can('logAction', $reservation)) {
            return $this->deny('mutation_policy.sales_reservation_log_action_denied', [
                'sales_reservation_id' => $reservationId,
            ]);
        }

        return new SafeWriteMutationPolicyDecision(true, 'mutation_policy.allowed', [
            'sales_reservation_id' => $reservationId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function evaluateCreditBookingClientContactLog(User $user, array $input): SafeWriteMutationPolicyDecision
    {
        if (! $user->can('credit.bookings.manage')) {
            return $this->deny('mutation_policy.permission_credit_bookings_manage');
        }

        $definition = $this->skillRegistry->find(self::PAIRED_SKILL_KEY);
        if ($definition === null) {
            return $this->deny('mutation_policy.paired_skill_missing');
        }

        if (! $this->skillRegistry->isEnabled($definition)) {
            return $this->deny('mutation_policy.paired_skill_disabled');
        }

        $capabilities = $this->capabilityResolver->resolve($user);
        $sectionKey = (string) ($definition['section_key'] ?? 'general');

        if (! $this->passesSectionGate($sectionKey, $capabilities)) {
            return $this->deny('mutation_policy.section_capabilities', ['section_key' => $sectionKey]);
        }

        if (! $this->skillRegistry->hasRequiredPermissions($user, $definition)) {
            return $this->deny('mutation_policy.skill_permissions');
        }

        if (! $this->skillRegistry->hasRequiredCapabilities($definition, $capabilities)) {
            return $this->deny('mutation_policy.skill_capabilities');
        }

        $reservationId = $this->extractCreditReservationId($input);
        if ($reservationId === null || $reservationId < 1) {
            return $this->deny('mutation_policy.reservation_id_required');
        }

        $reservation = SalesReservation::query()->find($reservationId);
        if ($reservation === null) {
            return $this->deny('mutation_policy.reservation_not_found', ['sales_reservation_id' => $reservationId]);
        }

        if (! $this->actorMayAccessReservationForMutation($user, $reservation)) {
            return $this->deny('mutation_policy.record_access_denied', ['sales_reservation_id' => $reservationId]);
        }

        if ($reservation->status === 'cancelled') {
            return $this->deny('mutation_policy.reservation_cancelled', ['sales_reservation_id' => $reservationId]);
        }

        return new SafeWriteMutationPolicyDecision(true, 'mutation_policy.allowed', [
            'sales_reservation_id' => $reservationId,
        ]);
    }

    /**
     * @param  array<int, string>  $capabilities
     */
    private function passesSectionGate(string $sectionKey, array $capabilities): bool
    {
        $section = $this->sectionRegistry->find($sectionKey);
        if ($section === null) {
            return false;
        }

        $required = (array) ($section['required_capabilities'] ?? []);

        return $required === [] || empty(array_diff($required, $capabilities));
    }

    /**
     * Stricter than draft row-scope: requires policy or credit oversight permissions.
     */
    private function actorMayAccessReservationForMutation(User $user, SalesReservation $reservation): bool
    {
        if ($user->can('view', $reservation)) {
            return true;
        }

        return $user->can('credit.bookings.view') && $user->can('credit.bookings.manage');
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function extractCreditReservationId(array $input): ?int
    {
        $proposal = (array) ($input['proposal'] ?? []);
        $fromPayload = $proposal['draft']['payload']['sales_reservation_id'] ?? null;
        if ($fromPayload !== null && (int) $fromPayload > 0) {
            return (int) $fromPayload;
        }

        $path = (string) ($proposal['flow']['handoff']['path'] ?? '');
        if ($path !== '' && preg_match('#/credit/bookings/(\d+)/actions#', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function deny(string $reason, array $context = []): SafeWriteMutationPolicyDecision
    {
        return new SafeWriteMutationPolicyDecision(false, $reason, $context);
    }
}
