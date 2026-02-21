<?php

namespace Tests\Unit\AI;

use App\Models\User;
use App\Services\AI\AiRagService;
use App\Services\AI\VectorStore\VectorStoreInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AiRagPermissionFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Permission::findOrCreate('marketing.projects.view');
        Permission::findOrCreate('contracts.view');
    }

    public function test_deny_by_default_when_permissions_any_of_missing(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('marketing.projects.view');

        $store = new class implements VectorStoreInterface {
            public function search(string $query, array $filters, int $limit): array
            {
                return [
                    ['chunk_id' => 1, 'document_id' => 1, 'content' => 'No access meta', 'meta' => []],
                    ['chunk_id' => 2, 'document_id' => 2, 'content' => 'Empty permissions', 'meta' => ['access' => ['permissions_any_of' => []]]],
                ];
            }

            public function upsertChunks(array $chunks): void {}
        };

        $rag = new AiRagService($store);
        $sources = $rag->search($user, 'test', [], 10);
        $this->assertCount(0, $sources);
    }

    public function test_returns_chunks_when_user_has_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('marketing.projects.view');

        $store = new class implements VectorStoreInterface {
            public function search(string $query, array $filters, int $limit): array
            {
                return [
                    [
                        'chunk_id' => 1,
                        'document_id' => 1,
                        'content' => 'Lead summary',
                        'meta' => [
                            'access' => ['permissions_any_of' => ['marketing.projects.view']],
                            'title' => 'Lead',
                            'source_uri' => 'lead/1',
                            'type' => 'record',
                        ],
                    ],
                ];
            }

            public function upsertChunks(array $chunks): void {}
        };

        $rag = new AiRagService($store);
        $sources = $rag->search($user, 'test', [], 10);
        $this->assertCount(1, $sources);
        $this->assertEquals('Lead', $sources[0]['title']);
    }

    public function test_filters_out_is_deleted_chunks(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('marketing.projects.view');

        $store = new class implements VectorStoreInterface {
            public function search(string $query, array $filters, int $limit): array
            {
                return [
                    [
                        'chunk_id' => 1,
                        'document_id' => 1,
                        'content' => 'Deleted',
                        'meta' => [
                            'access' => ['permissions_any_of' => ['marketing.projects.view']],
                            'is_deleted' => true,
                            'title' => 'Deleted',
                            'source_uri' => 'lead/1',
                        ],
                    ],
                ];
            }

            public function upsertChunks(array $chunks): void {}
        };

        $rag = new AiRagService($store);
        $sources = $rag->search($user, 'test', [], 10);
        $this->assertCount(0, $sources);
    }
}
