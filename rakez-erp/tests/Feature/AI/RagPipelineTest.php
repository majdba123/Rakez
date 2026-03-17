<?php

namespace Tests\Feature\AI;

use App\Models\AiChunk;
use App\Models\AiDocument;
use App\Services\AI\Rag\DocumentIngestionService;
use App\Services\AI\Rag\EmbeddingService;
use App\Services\AI\Rag\TextChunkerService;
use App\Services\AI\Rag\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RagPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingest_text_creates_document_and_chunks(): void
    {
        $fakeEmbedding = array_fill(0, 1536, 0.1);

        $mockEmbedding = Mockery::mock(EmbeddingService::class);
        $mockEmbedding->shouldReceive('embedBatch')
            ->once()
            ->andReturn([$fakeEmbedding]);
        $this->app->instance(EmbeddingService::class, $mockEmbedding);

        $mockAnalyzer = Mockery::mock(\App\Services\AI\Rag\DocumentAnalyzerService::class);
        $this->app->instance(\App\Services\AI\Rag\DocumentAnalyzerService::class, $mockAnalyzer);

        $service = app(DocumentIngestionService::class);

        $document = $service->ingestText(
            text: 'This is a short test document about real estate in Saudi Arabia.',
            title: 'Test Document',
            source: 'test',
        );

        $this->assertInstanceOf(AiDocument::class, $document);
        $this->assertEquals('Test Document', $document->title);
        $this->assertGreaterThan(0, $document->chunkCount());
    }

    public function test_vector_search_returns_relevant_results(): void
    {
        // Create document with known embeddings
        $doc = AiDocument::create([
            'title' => 'Real Estate Guide',
            'source' => 'test',
            'mime_type' => 'text/plain',
        ]);

        // Create chunks with similar embeddings
        $relevantEmbedding = array_fill(0, 1536, 0.5);
        $irrelevantEmbedding = array_fill(0, 1536, -0.5);

        AiChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'content_text' => 'Guide to buying real estate in Riyadh',
            'tokens' => 8,
            'content_hash' => hash('sha256', 'relevant'),
            'embedding_json' => $relevantEmbedding,
        ]);

        AiChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 1,
            'content_text' => 'Unrelated content about cooking',
            'tokens' => 5,
            'content_hash' => hash('sha256', 'irrelevant'),
            'embedding_json' => $irrelevantEmbedding,
        ]);

        $service = new VectorSearchService;

        // Query with embedding similar to relevant chunk
        $queryEmbedding = array_fill(0, 1536, 0.5);
        $results = $service->search($queryEmbedding, 5, 0.5);

        $this->assertGreaterThan(0, $results->count());
        // First result should be the relevant chunk
        $this->assertStringContainsString('real estate', $results->first()->content_text);
    }

    public function test_vector_search_respects_min_similarity(): void
    {
        $doc = AiDocument::create([
            'title' => 'Test',
            'source' => 'test',
            'mime_type' => 'text/plain',
        ]);

        AiChunk::create([
            'document_id' => $doc->id,
            'chunk_index' => 0,
            'content_text' => 'Some content',
            'tokens' => 2,
            'content_hash' => hash('sha256', 'test'),
            'embedding_json' => array_fill(0, 1536, -0.5),
        ]);

        $service = new VectorSearchService;

        // Query with opposite embedding → low similarity
        $queryEmbedding = array_fill(0, 1536, 0.5);
        $results = $service->search($queryEmbedding, 5, 0.9);

        $this->assertEquals(0, $results->count());
    }

    public function test_vector_search_filters_by_document_id(): void
    {
        $doc1 = AiDocument::create(['title' => 'Doc 1', 'source' => 'test', 'mime_type' => 'text/plain']);
        $doc2 = AiDocument::create(['title' => 'Doc 2', 'source' => 'test', 'mime_type' => 'text/plain']);

        $embedding = array_fill(0, 1536, 0.5);

        AiChunk::create([
            'document_id' => $doc1->id,
            'chunk_index' => 0,
            'content_text' => 'Content in doc 1',
            'tokens' => 4,
            'content_hash' => hash('sha256', 'doc1'),
            'embedding_json' => $embedding,
        ]);

        AiChunk::create([
            'document_id' => $doc2->id,
            'chunk_index' => 0,
            'content_text' => 'Content in doc 2',
            'tokens' => 4,
            'content_hash' => hash('sha256', 'doc2'),
            'embedding_json' => $embedding,
        ]);

        $service = new VectorSearchService;
        $results = $service->search($embedding, 10, 0.5, $doc1->id);

        foreach ($results as $chunk) {
            $this->assertEquals($doc1->id, $chunk->document_id);
        }
    }

    public function test_text_chunker_integrated_with_ingestion(): void
    {
        $chunker = new TextChunkerService;

        // Create long Arabic text
        $text = str_repeat("هذا نص تجريبي عن العقارات في المملكة العربية السعودية. يتضمن معلومات عن المشاريع والأسعار.\n\n", 30);

        $chunks = $chunker->chunk($text, 200, 30);

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertNotEmpty($chunk['text']);
            $this->assertLessThanOrEqual(300, $chunk['tokens']); // With some tolerance
        }
    }
}
