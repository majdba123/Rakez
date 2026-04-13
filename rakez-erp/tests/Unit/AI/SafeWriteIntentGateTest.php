<?php

namespace Tests\Unit\AI;

use App\Services\AI\SafeWrites\SafeWriteActionRegistry;
use App\Services\AI\SafeWrites\SafeWriteIntentGate;
use Tests\TestCase;

class SafeWriteIntentGateTest extends TestCase
{
    private SafeWriteActionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new SafeWriteActionRegistry();
    }

    public function test_denies_by_default_when_global_switch_is_off(): void
    {
        config(['safe_write_intent.enabled' => false]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('credit_booking.client_contact.log');
        $this->assertNotNull($action);

        $decision = $gate->evaluate($action);
        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.global_disabled', $decision->reason);
    }

    public function test_denies_when_per_action_execution_flag_is_off(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => false,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('credit_booking.client_contact.log');
        $decision = $gate->evaluate($action);

        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.action_flag_disabled', $decision->reason);
    }

    public function test_denies_when_action_is_not_on_allowlist(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('task.create');
        $this->assertNotNull($action);

        $decision = $gate->evaluate($action);
        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.not_allowlisted', $decision->reason);
    }

    public function test_denies_when_not_intent_execution_candidate_even_if_allowlisted(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['task.create', 'credit_booking.client_contact.log'],
            'safe_write_intent.actions.task.create' => true,
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('task.create');
        $decision = $gate->evaluate($action);

        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.not_execution_candidate', $decision->reason);
    }

    public function test_denies_draft_only_registry_actions_without_candidate_flag(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['lead.create', 'credit_booking.client_contact.log'],
            'safe_write_intent.actions.lead.create' => true,
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $decision = $gate->evaluate($this->registry->find('lead.create'));

        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.not_execution_candidate', $decision->reason);
    }

    public function test_allows_only_credit_candidate_when_all_gate_conditions_satisfied(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('credit_booking.client_contact.log');
        $decision = $gate->evaluate($action);

        $this->assertTrue($decision->allowed);
        $this->assertSame('intent_gate.allowed', $decision->reason);
    }

    public function test_denies_missing_action_key(): void
    {
        config(['safe_write_intent.enabled' => true]);

        $gate = new SafeWriteIntentGate;
        $decision = $gate->evaluate(['key' => '']);

        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.missing_action_key', $decision->reason);
    }

    public function test_denies_forbidden_activation_state(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['contract.create'],
            'safe_write_intent.actions.contract.create' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = array_merge($this->registry->find('contract.create'), [
            'intent_execution_candidate' => true,
        ]);

        $decision = $gate->evaluate($action);
        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.activation_state_blocked', $decision->reason);
    }

    public function test_denies_confirmation_only_disabled_even_when_candidate_flag_true(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['waiting_list.create'],
            'safe_write_intent.actions.waiting_list.create' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = array_merge($this->registry->find('waiting_list.create'), [
            'intent_execution_candidate' => true,
            'draft_flow_key' => 'x',
            'reusable_surface' => 'App\\Services\\Sales\\WaitingListService::createWaitingListEntry',
            'handler' => \App\Services\AI\SafeWrites\Handlers\MetadataOnlySafeWriteActionHandler::class,
        ]);

        $decision = $gate->evaluate($action);
        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.activation_state_blocked', $decision->reason);
    }

    public function test_denies_invalid_registry_metadata(): void
    {
        config([
            'safe_write_intent.enabled' => true,
            'safe_write_intent.allowlisted_keys' => ['credit_booking.client_contact.log'],
            'safe_write_intent.actions.credit_booking.client_contact.log' => true,
        ]);

        $gate = new SafeWriteIntentGate;
        $action = $this->registry->find('credit_booking.client_contact.log');
        $action['reusable_surface'] = '';

        $decision = $gate->evaluate($action);
        $this->assertFalse($decision->allowed);
        $this->assertSame('intent_gate.invalid_registry_metadata', $decision->reason);
    }
}
