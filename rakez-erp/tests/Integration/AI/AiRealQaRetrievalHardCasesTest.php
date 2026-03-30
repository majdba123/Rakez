<?php

namespace Tests\Integration\AI;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Services\AI\Rag\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;
use Tests\Traits\CreatesUsersWithBootstrapRole;
use Tests\Traits\ReadsDotEnvForTest;
use Tests\Traits\TestsWithRealOpenAiConnection;

#[Group('ai-e2e-real')]
#[Group('ai-qa-hard-proof')]
class AiRealQaRetrievalHardCasesTest extends TestCase
{
    use CreatesUsersWithBootstrapRole;
    use ReadsDotEnvForTest;
    use RefreshDatabase;
    use TestsWithRealOpenAiConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealOpenAiFromDotEnv();
    }

    public function test_retrieval_relevant_semantic_case_returns_relevant_result(): void
    {
        $admin = $this->createUserWithBootstrapRole('admin');
        Sanctum::actingAs($admin);

        $doc = $this->createEmbeddedDoc($admin->id, 'QA-Doc-1', 'مشروع برج الياقوت 77 في الرياض مع خطة سداد مرنة.');

        $response = $this->postJson('/api/ai/documents/search', [
            'query' => 'برج الياقوت 77 في الرياض',
            'limit' => 5,
            'document_id' => $doc->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.total'));
        $snippet = (string) ($response->json('data.results.0.snippet') ?? '');
        $this->assertStringContainsString('الياقوت', $snippet);
    }

    public function test_retrieval_zero_result_case_is_handled_without_fabrication(): void
    {
        $admin = $this->createUserWithBootstrapRole('admin');
        Sanctum::actingAs($admin);
        $this->createEmbeddedDoc($admin->id, 'QA-Doc-2', 'سياسة تسويق رقمية لحملات سناب وميتا.');

        $response = $this->postJson('/api/ai/documents/search', [
            'query' => 'ZZZ_NON_EXISTENT_TOPIC_918273645',
            'limit' => 5,
        ]);
        $response->assertOk();
        $this->assertSame(0, (int) $response->json('data.total'));
        $this->assertIsArray($response->json('data.results'));
    }

    public function test_retrieval_unauthorized_document_scope_is_blocked(): void
    {
        $owner = $this->createUserWithBootstrapRole('sales');
        $other = $this->createUserWithBootstrapRole('marketing');
        $doc = $this->createEmbeddedDoc($owner->id, 'Owner-Doc', 'وثيقة حساسة خاصة بمالك المستند.');

        Sanctum::actingAs($other);
        $response = $this->postJson('/api/ai/documents/search', [
            'query' => 'وثيقة حساسة',
            'document_id' => $doc->id,
            'limit' => 5,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('success', false);
    }

    public function test_retrieval_mixed_permission_dataset_returns_only_owned_docs_for_non_admin(): void
    {
        $sales = $this->createUserWithBootstrapRole('sales');
        $other = $this->createUserWithBootstrapRole('marketing');

        $owned = $this->createEmbeddedDoc($sales->id, 'Owned', 'تقرير ليدات مشروع النخيل.');
        $foreign = $this->createEmbeddedDoc($other->id, 'Foreign', 'تقرير ليدات مشروع النخيل.');

        Sanctum::actingAs($sales);
        $response = $this->postJson('/api/ai/documents/search', [
            'query' => 'ليدات مشروع النخيل',
            'limit' => 10,
        ]);
        $response->assertOk();

        $ids = collect($response->json('data.results'))->pluck('document_id')->unique()->values()->all();
        $this->assertContains($owned->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_documents_index_and_show_enforce_scope(): void
    {
        $owner = $this->createUserWithBootstrapRole('sales');
        $other = $this->createUserWithBootstrapRole('marketing');
        $doc = $this->createEmbeddedDoc($owner->id, 'Scoped-Doc', 'محتوى خاص بالمالك فقط.');

        Sanctum::actingAs($owner);
        $indexOwner = $this->getJson('/api/ai/documents');
        $indexOwner->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $indexOwner->json('data.total'));

        Sanctum::actingAs($other);
        $showOther = $this->getJson("/api/ai/documents/{$doc->id}");
        $showOther->assertStatus(403);
    }

    private function createEmbeddedDoc(int $ownerId, string $title, string $text): AiDocument
    {
        $doc = AiDocument::create([
            'uploaded_by_user_id' => $ownerId,
            'title' => $title,
            'source' => 'qa-hard-proof',
            'mime_type' => 'text/plain',
            'meta_json' => ['qa' => true],
        ]);

        $embedding = app(EmbeddingService::class)->embed($text);
        AiChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'content_text' => $text,
            'meta_json' => ['qa' => true],
            'tokens' => max(10, (int) (mb_strlen($text) / 3)),
            'content_hash' => hash('sha256', $text),
            'embedding_json' => $embedding,
        ]);

        return $doc;
    }
}

