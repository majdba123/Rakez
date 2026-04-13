<?php

namespace Tests\Unit\AI;

use App\Services\AI\SafeWrites\SalesReservationActionProposalBinder;
use Tests\TestCase;

class SalesReservationActionProposalBinderTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function baseProposal(array $overrides = []): array
    {
        $base = [
            'flow' => [
                'key' => 'log_reservation_action_draft',
                'handoff' => [
                    'method' => 'POST',
                    'path' => '/api/sales/reservations/42/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => 42,
                    'action_type' => 'persuasion',
                    'notes' => 'Follow-up call',
                ],
            ],
        ];

        return array_replace_recursive($base, $overrides);
    }

    public function test_validate_binding_succeeds_when_path_id_matches_payload_and_action_type_canonical(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $this->assertNull($binder->validateBindingShape($this->baseProposal()));
    }

    public function test_validate_binding_fails_when_path_reservation_id_differs_from_payload(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'flow' => [
                'handoff' => [
                    'path' => '/api/sales/reservations/99/actions',
                ],
            ],
        ]);

        $this->assertSame(
            'proposal_binding.handoff_path_reservation_mismatch',
            $binder->validateBindingShape($p)
        );
    }

    public function test_missing_handoff_path_fails(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal();
        $p['flow']['handoff']['path'] = '';

        $this->assertSame('proposal_binding.invalid_handoff_path', $binder->validateBindingShape($p));
    }

    public function test_malformed_handoff_path_fails(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'flow' => [
                'handoff' => [
                    'path' => '/api/sales/other/42/actions',
                ],
            ],
        ]);

        $this->assertSame('proposal_binding.invalid_handoff_path', $binder->validateBindingShape($p));
    }

    public function test_valid_canonical_action_types_pass(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        foreach (['lead_acquisition', 'persuasion', 'closing'] as $type) {
            $p = $this->baseProposal([
                'draft' => [
                    'payload' => [
                        'action_type' => $type,
                    ],
                ],
            ]);
            $this->assertNull($binder->validateBindingShape($p), $type);
        }
    }

    public function test_invalid_action_type_rejected(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'malicious_type',
                ],
            ],
        ]);

        $this->assertSame('proposal_binding.invalid_action_type', $binder->validateBindingShape($p));
    }

    public function test_arabic_action_type_normalized_like_store_request(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'إقناع',
                ],
            ],
        ]);

        $this->assertNull($binder->validateBindingShape($p));
        $this->assertSame('persuasion', $binder->bindingPayload($p)['action_type']);
    }

    public function test_derive_stable_idempotency_is_null_when_invalid(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $bad = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'x',
                ],
            ],
        ]);

        $this->assertNull($binder->deriveStableIdempotencyKey($bad));
    }

    public function test_idempotency_changes_when_action_type_changes(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $a = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'lead_acquisition',
                    'notes' => 'same',
                ],
            ],
        ]);
        $b = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'closing',
                    'notes' => 'same',
                ],
            ],
        ]);

        $ka = $binder->deriveStableIdempotencyKey($a);
        $kb = $binder->deriveStableIdempotencyKey($b);
        $this->assertNotNull($ka);
        $this->assertNotNull($kb);
        $this->assertNotSame($ka, $kb);
    }

    public function test_idempotency_changes_when_reservation_id_changes(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $a = $this->baseProposal([
            'flow' => [
                'handoff' => [
                    'path' => '/api/sales/reservations/1/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => 1,
                    'action_type' => 'persuasion',
                    'notes' => 'n',
                ],
            ],
        ]);
        $b = $this->baseProposal([
            'flow' => [
                'handoff' => [
                    'path' => '/api/sales/reservations/2/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => 2,
                    'action_type' => 'persuasion',
                    'notes' => 'n',
                ],
            ],
        ]);

        $this->assertNotSame(
            $binder->deriveStableIdempotencyKey($a),
            $binder->deriveStableIdempotencyKey($b)
        );
    }

    public function test_deterministic_idempotency_for_identical_proposals(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal();

        $this->assertSame(
            $binder->deriveStableIdempotencyKey($p),
            $binder->deriveStableIdempotencyKey($p)
        );
        $this->assertStringStartsWith('sw_sra:', $binder->deriveStableIdempotencyKey($p) ?? '');
    }

    public function test_binding_payload_includes_action_type_in_fingerprint_inputs(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal();

        $this->assertSame(
            $binder->fingerprint($p),
            $binder->fingerprint($p)
        );

        $q = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => 'closing',
                ],
            ],
        ]);

        $this->assertNotSame($binder->fingerprint($p), $binder->fingerprint($q));
    }

    public function test_notes_too_long_fails(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'notes' => str_repeat('a', 65536),
                ],
            ],
        ]);

        $this->assertSame('proposal_binding.notes_too_long', $binder->validateBindingShape($p));
    }

    public function test_missing_action_type_fails(): void
    {
        $binder = new SalesReservationActionProposalBinder;
        $p = $this->baseProposal([
            'draft' => [
                'payload' => [
                    'action_type' => '',
                ],
            ],
        ]);

        $this->assertSame('proposal_binding.missing_action_type', $binder->validateBindingShape($p));
    }
}
