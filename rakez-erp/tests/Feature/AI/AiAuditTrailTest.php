<?php

namespace Tests\Feature\AI;

use App\Models\AiAuditEntry;
use App\Models\User;
use App\Services\AI\AiAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_entry_is_created(): void
    {
        $user = User::factory()->create();
        $service = new AiAuditService;

        $entry = $service->record(
            user: $user,
            action: 'query',
            resourceType: 'lead',
            resourceId: 123,
            input: ['message' => 'test query'],
            output: ['answer' => 'test response'],
        );

        $this->assertInstanceOf(AiAuditEntry::class, $entry);
        $this->assertEquals($user->id, $entry->user_id);
        $this->assertEquals('query', $entry->action);
        $this->assertEquals('lead', $entry->resource_type);
        $this->assertEquals(123, $entry->resource_id);
        $this->assertDatabaseHas('ai_audit_trail', [
            'user_id' => $user->id,
            'action' => 'query',
        ]);
    }

    public function test_audit_entry_truncates_long_summaries(): void
    {
        $user = User::factory()->create();
        $service = new AiAuditService;

        $longInput = ['message' => str_repeat('A', 2000)];

        $entry = $service->record(
            user: $user,
            action: 'tool_call',
            input: $longInput,
        );

        $this->assertLessThanOrEqual(1003, strlen($entry->input_summary)); // 1000 + '...'
    }

    public function test_audit_entries_for_different_actions(): void
    {
        $user = User::factory()->create();
        $service = new AiAuditService;

        $service->record($user, 'query');
        $service->record($user, 'tool_call');
        $service->record($user, 'document_upload');

        $this->assertEquals(3, AiAuditEntry::where('user_id', $user->id)->count());
    }
}
