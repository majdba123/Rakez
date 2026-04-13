<?php

namespace Tests\Unit\AI;

use App\Services\AI\SafeWrites\CreditClientContactProposalBinder;
use Tests\TestCase;

class CreditClientContactProposalBinderIdempotencyTest extends TestCase
{
    public function test_derive_stable_idempotency_returns_null_when_shape_invalid(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $this->assertNull($binder->deriveStableIdempotencyKey([]));
    }

    public function test_derive_stable_idempotency_is_deterministic_for_same_binding(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $proposal = [
            'flow' => [
                'key' => 'log_credit_client_contact_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/credit/bookings/12/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => 12,
                    'notes' => 'Hello',
                ],
            ],
        ];

        $a = $binder->deriveStableIdempotencyKey($proposal);
        $b = $binder->deriveStableIdempotencyKey($proposal);
        $this->assertNotNull($a);
        $this->assertSame($a, $b);
        $this->assertStringStartsWith('sw_cc:', $a);
    }

    public function test_different_notes_changes_stable_idempotency(): void
    {
        $binder = new CreditClientContactProposalBinder;
        $base = [
            'flow' => [
                'key' => 'log_credit_client_contact_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/credit/bookings/12/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => 12,
                    'notes' => 'A',
                ],
            ],
        ];
        $other = $base;
        $other['draft']['payload']['notes'] = 'B';

        $this->assertNotSame(
            $binder->deriveStableIdempotencyKey($base),
            $binder->deriveStableIdempotencyKey($other)
        );
    }
}
