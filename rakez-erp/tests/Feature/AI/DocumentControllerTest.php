<?php

namespace Tests\Feature\AI;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authenticateAdmin(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('use-ai-assistant');
        $user->assignRole('admin');
        Sanctum::actingAs($user);

        return $user;
    }

    private function seedDocument(string $title = 'Test Doc', ?User $owner = null): AiDocument
    {
        $owner = $owner ?? auth()->user();

        $doc = AiDocument::create([
            'uploaded_by_user_id' => $owner?->id,
            'title' => $title,
            'source' => 'test.txt',
            'mime_type' => 'text/plain',
            'meta_json' => ['test' => true],
        ]);

        AiChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'content_text' => 'Sample content for testing.',
            'meta_json' => ['source' => 'test'],
            'tokens' => 6,
            'content_hash' => hash('sha256', 'Sample content for testing.'),
            'embedding_json' => array_fill(0, 1536, 0.1),
        ]);

        return $doc;
    }

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/ai/documents', []);
        $response->assertUnauthorized();
    }

    public function test_upload_validates_file_required(): void
    {
        $this->authenticateAdmin();

        $response = $this->postJson('/api/ai/documents', []);
        $response->assertStatus(422);
    }

    public function test_list_documents(): void
    {
        $this->authenticateAdmin();
        $this->seedDocument('Doc 1');
        $this->seedDocument('Doc 2');

        $response = $this->getJson('/api/ai/documents');

        $response->assertOk();
        $response->assertJson(['success' => true]);
    }

    public function test_show_document(): void
    {
        $this->authenticateAdmin();
        $doc = $this->seedDocument();

        $response = $this->getJson("/api/ai/documents/{$doc->id}");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $doc->id,
                'title' => 'Test Doc',
            ],
        ]);
    }

    public function test_show_nonexistent_document(): void
    {
        $this->authenticateAdmin();

        $response = $this->getJson('/api/ai/documents/99999');
        $response->assertNotFound();
    }

    public function test_delete_document(): void
    {
        $this->authenticateAdmin();
        $doc = $this->seedDocument();

        $response = $this->deleteJson("/api/ai/documents/{$doc->id}");

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->assertDatabaseMissing('ai_documents', ['id' => $doc->id]);
        $this->assertDatabaseMissing('ai_chunks', ['document_id' => $doc->id]);
    }

    public function test_search_requires_query(): void
    {
        $this->authenticateAdmin();

        $response = $this->postJson('/api/ai/documents/search', []);
        $response->assertStatus(422);
    }

    public function test_search_with_mocked_embedding(): void
    {
        $this->authenticateAdmin();
        $this->seedDocument();

        $fakeEmbedding = array_fill(0, 1536, 0.1);
        OpenAI::fake([
            EmbeddingResponse::fake([
                'data' => [
                    ['object' => 'embedding', 'embedding' => $fakeEmbedding, 'index' => 0],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/ai/documents/search', [
            'query' => 'test content',
            'limit' => 5,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'data' => ['query', 'results', 'total'],
        ]);
    }
}
