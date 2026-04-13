<?php

namespace Tests\Unit\AI;

use App\Services\AI\SafeWrites\CreditClientContactProposalBinder;
use Tests\TestCase;

class CreditClientContactProposalBinderValidationTest extends TestCase
{
    private function validProposalSkeleton(int $reservationId, string $path): array
    {
        return [
            'flow' => [
                'key' => 'log_credit_client_contact_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => $path,
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => $reservationId,
                    'notes' => 'ok',
                ],
            ],
        ];
    }

    public function test_validate_binding_succeeds_when_path_id_matches_payload_id(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $this->assertNull($binder->validateBindingShape($this->validProposalSkeleton(42, '/api/credit/bookings/42/actions')));
    }

    public function test_validate_binding_fails_when_path_id_differs_from_payload_id(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $this->assertSame(
            'proposal_binding.handoff_path_reservation_mismatch',
            $binder->validateBindingShape($this->validProposalSkeleton(42, '/api/credit/bookings/99/actions'))
        );
    }

    public function test_derive_stable_idempotency_is_null_when_path_payload_mismatch(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $this->assertNull($binder->deriveStableIdempotencyKey(
            $this->validProposalSkeleton(1, '/api/credit/bookings/2/actions')
        ));
    }

    public function test_missing_handoff_path_fails_with_invalid_handoff_path(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $p = $this->validProposalSkeleton(1, '/api/credit/bookings/1/actions');
        $p['flow']['handoff']['path'] = '';

        $this->assertSame('proposal_binding.invalid_handoff_path', $binder->validateBindingShape($p));
    }

    public function test_malformed_handoff_path_without_booking_segment_fails(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $p = $this->validProposalSkeleton(5, '/api/credit/other/5/actions');

        $this->assertSame('proposal_binding.invalid_handoff_path', $binder->validateBindingShape($p));
    }
}
