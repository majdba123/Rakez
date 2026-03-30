<?php

namespace Tests\Integration\AI;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Services\AI\Rag\DocumentIngestionService;
use App\Services\AI\Rag\EmbeddingService;
use App\Services\AI\Rag\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\ReadsDotEnvForTest;

/**
 * End-to-end RAG pipeline test with real OpenAI API calls.
 * Requires OPENAI_API_KEY and AI_REAL_TESTS=true in .env.
 *
 * @group integration
 * @group ai-e2e-real
 */
class RagEndToEndTest extends TestCase
{
    use ReadsDotEnvForTest;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $realKey = $this->envFromDotFile('OPENAI_API_KEY');

        if (! $realKey || str_starts_with($realKey, 'test-fake')) {
            $this->markTestSkipped('Real OPENAI_API_KEY not available — skipping integration test.');
        }

        if (! $this->envFromDotFileIsTrue('AI_REAL_TESTS')) {
            $this->markTestSkipped('AI_REAL_TESTS is not enabled — set AI_REAL_TESTS=true in .env to run');
        }

        config(['openai.api_key' => $realKey]);
    }

    public function test_full_rag_pipeline_ingest_and_search(): void
    {
        $ingestionService = app(DocumentIngestionService::class);

        // 1. Ingest a document
        $document = $ingestionService->ingestText(
            text: "سياسة الإلغاء: يمكن للعميل إلغاء الحجز خلال 48 ساعة من تاريخ الحجز واسترداد كامل المبلغ. بعد 48 ساعة يتم خصم 10% كرسوم إدارية.\n\nسياسة التمويل: يجب ألا يتجاوز القسط الشهري 55% من دخل العميل حسب معايير ساما.",
            title: 'سياسات الشركة',
            source: 'integration_test',
        );

        $this->assertInstanceOf(AiDocument::class, $document);
        $this->assertGreaterThan(0, $document->chunkCount());

        // Verify embeddings were generated
        $chunksWithEmbeddings = AiChunk::forDocument($document->id)->withEmbeddings()->count();
        $this->assertGreaterThan(0, $chunksWithEmbeddings);

        // 2. Search for relevant content
        $embeddingService = app(EmbeddingService::class);
        $searchService = new VectorSearchService;

        $queryEmbedding = $embeddingService->embed('ما هي سياسة إلغاء الحجز؟');
        $results = $searchService->search($queryEmbedding, 5, 0.5);

        $this->assertGreaterThan(0, $results->count());

        // The first result should contain cancellation policy content
        $firstResult = $results->first();
        $this->assertNotNull($firstResult);
        $this->assertStringContainsString('إلغاء', $firstResult->content_text);

        // 3. Clean up
        $ingestionService->delete($document);
        $this->assertDatabaseMissing('ai_documents', ['id' => $document->id]);
    }

    public function test_rag_search_returns_ranked_results(): void
    {
        $ingestionService = app(DocumentIngestionService::class);

        $document = $ingestionService->ingestText(
            text: "المبيعات: يتم تتبع أداء فريق المبيعات من خلال مؤشرات KPI تشمل معدل الإغلاق وعدد المكالمات.\n\nالتسويق: يتم قياس أداء الحملات التسويقية من خلال تكلفة الليد والعائد على الاستثمار التسويقي.\n\nالموارد البشرية: يتم تقييم الموظفين سنوياً بناءً على الأداء والالتزام.",
            title: 'دليل الأقسام',
            source: 'integration_test',
        );

        $embeddingService = app(EmbeddingService::class);
        $searchService = new VectorSearchService;

        // Search for marketing-related content
        $queryEmbedding = $embeddingService->embed('ما هي مؤشرات أداء التسويق؟');
        $results = $searchService->search($queryEmbedding, 5, 0.3);

        $this->assertGreaterThan(0, $results->count());

        // Results should be sorted by similarity (descending)
        $similarities = $results->pluck('similarity')->toArray();
        for ($i = 1; $i < count($similarities); $i++) {
            $this->assertGreaterThanOrEqual($similarities[$i], $similarities[$i - 1]);
        }

        $ingestionService->delete($document);
    }
}
