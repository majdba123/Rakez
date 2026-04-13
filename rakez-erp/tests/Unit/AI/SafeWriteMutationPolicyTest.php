<?php

namespace Tests\Unit\AI;

use App\Models\SalesReservation;
use App\Models\User;
use App\Services\AI\SafeWrites\SafeWriteMutationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SafeWriteMutationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'use-ai-assistant',
            'sales.reservations.view',
            'credit.bookings.view',
            'credit.bookings.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('admin', 'web');
    }

    /**
     * @return array<string, mixed>
     */
    private function validSalesReservationActionProposal(SalesReservation $reservation, string $actionType = 'persuasion'): array
    {
        return [
            'flow' => [
                'key' => 'log_reservation_action_draft',
                'handoff' => [
                    'path' => '/api/sales/reservations/'.$reservation->id.'/actions',
                ],
            ],
            'draft' => [
                'payload' => [
                    'sales_reservation_id' => $reservation->id,
                    'action_type' => $actionType,
                    'notes' => 'Test note',
                ],
            ],
        ];
    }

    public function test_default_deny_for_unsupported_action_key(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'task.create', []);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.unsupported_action', $decision->reason);
    }

    public function test_allowed_for_authorized_actor_and_valid_reservation(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => $reservation->id,
                    ],
                ],
                'flow' => [
                    'handoff' => [
                        'path' => '/api/credit/bookings/'.$reservation->id.'/actions',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($decision->allowed);
        $this->assertSame('mutation_policy.allowed', $decision->reason);
    }

    public function test_reservation_id_can_be_resolved_from_handoff_path_only(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'flow' => [
                    'handoff' => [
                        'path' => '/api/credit/bookings/'.$reservation->id.'/actions',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($decision->allowed);
    }

    public function test_denied_when_reservation_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => 999_999_999,
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.reservation_not_found', $decision->reason);
    }

    public function test_denied_when_actor_lacks_credit_bookings_manage(): void
    {
        $reservation = SalesReservation::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('credit.bookings.view');

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => ['sales_reservation_id' => $reservation->id],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.permission_credit_bookings_manage', $decision->reason);
    }

    public function test_denied_when_actor_lacks_credit_bookings_view_for_skill_capabilities(): void
    {
        $reservation = SalesReservation::factory()->create();

        $user = User::factory()->create();
        $user->givePermissionTo('credit.bookings.manage');

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => ['sales_reservation_id' => $reservation->id],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.section_capabilities', $decision->reason);
    }

    public function test_denied_when_sales_user_cannot_view_others_reservation_without_credit_permissions(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $actor = User::factory()->create();
        $actor->givePermissionTo(['sales.reservations.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($actor, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => ['sales_reservation_id' => $reservation->id],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.section_capabilities', $decision->reason);
    }

    public function test_denied_when_skill_capabilities_missing_even_if_permissions_present(): void
    {
        $reservation = SalesReservation::factory()->create([
            'status' => 'confirmed',
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);
        $user->setAttribute('capabilities', ['credit.bookings.view']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => ['sales_reservation_id' => $reservation->id],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.skill_capabilities', $decision->reason);
    }

    public function test_denied_when_reservation_cancelled(): void
    {
        $reservation = SalesReservation::factory()->cancelled()->create();

        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'draft' => [
                    'payload' => ['sales_reservation_id' => $reservation->id],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.reservation_cancelled', $decision->reason);
    }

    public function test_denied_when_reservation_id_missing_from_proposal(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(['credit.bookings.view', 'credit.bookings.manage']);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($user, 'credit_booking.client_contact.log', [
            'proposal' => [
                'flow' => [
                    'handoff' => [],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.reservation_id_required', $decision->reason);
    }

    public function test_sales_reservation_action_log_allowed_for_owner(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($owner, 'sales_reservation.action.log', [
            'proposal' => $this->validSalesReservationActionProposal($reservation),
        ]);

        $this->assertTrue($decision->allowed);
        $this->assertSame('mutation_policy.allowed', $decision->reason);
        $this->assertSame($reservation->id, $decision->context['sales_reservation_id'] ?? null);
    }

    public function test_sales_reservation_action_log_allowed_for_admin_on_foreign_reservation(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($admin, 'sales_reservation.action.log', [
            'proposal' => $this->validSalesReservationActionProposal($reservation),
        ]);

        $this->assertTrue($decision->allowed);
    }

    public function test_sales_reservation_action_log_denied_when_reservation_missing(): void
    {
        $owner = User::factory()->create();

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($owner, 'sales_reservation.action.log', [
            'proposal' => [
                'flow' => [
                    'key' => 'log_reservation_action_draft',
                    'handoff' => [
                        'path' => '/api/sales/reservations/999999999/actions',
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => 999_999_999,
                        'action_type' => 'persuasion',
                        'notes' => 'x',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.reservation_not_found', $decision->reason);
    }

    public function test_sales_reservation_action_log_denied_when_viewer_not_owner(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $other = User::factory()->create();
        $other->givePermissionTo('sales.reservations.view');

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($other, 'sales_reservation.action.log', [
            'proposal' => $this->validSalesReservationActionProposal($reservation),
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.sales_reservation_log_action_denied', $decision->reason);
    }

    public function test_sales_reservation_action_log_denied_when_proposal_fails_binder(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($owner, 'sales_reservation.action.log', [
            'proposal' => [
                'flow' => [
                    'key' => 'wrong_flow',
                    'handoff' => [
                        'path' => '/api/sales/reservations/'.$reservation->id.'/actions',
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => $reservation->id,
                        'action_type' => 'persuasion',
                        'notes' => 'x',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.proposal_binding_failed', $decision->reason);
        $this->assertSame('proposal_binding.invalid_flow_key', $decision->context['binding_reason'] ?? null);
    }

    public function test_sales_reservation_action_log_denied_when_path_and_payload_reservation_id_diverge(): void
    {
        $owner = User::factory()->create();
        $reservation = SalesReservation::factory()->create([
            'marketing_employee_id' => $owner->id,
            'status' => 'confirmed',
        ]);

        $policy = app(SafeWriteMutationPolicy::class);
        $decision = $policy->evaluate($owner, 'sales_reservation.action.log', [
            'proposal' => [
                'flow' => [
                    'key' => 'log_reservation_action_draft',
                    'handoff' => [
                        'path' => '/api/sales/reservations/'.($reservation->id + 1).'/actions',
                    ],
                ],
                'draft' => [
                    'payload' => [
                        'sales_reservation_id' => $reservation->id,
                        'action_type' => 'persuasion',
                        'notes' => 'x',
                    ],
                ],
            ],
        ]);

        $this->assertFalse($decision->allowed);
        $this->assertSame('mutation_policy.proposal_binding_failed', $decision->reason);
        $this->assertSame('proposal_binding.handoff_path_reservation_mismatch', $decision->context['binding_reason'] ?? null);
    }
}
