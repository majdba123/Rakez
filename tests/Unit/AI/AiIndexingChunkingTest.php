<?php

namespace Tests\Unit\AI;

use App\Services\AI\AiIndexingService;
use App\Services\AI\OpenAIEmbeddingClient;
use App\Services\AI\VectorStore\DisabledVectorStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiIndexingChunkingTest extends TestCase
{
    use RefreshDatabase;

    private AiIndexingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai_assistant.v2.chunking' => [
            'chunk_tokens_min' => 400,
            'chunk_tokens_max' => 800,
            'chunk_overlap_tokens' => 80,
        ]]);
        $this->service = new AiIndexingService(
            $this->createMock(OpenAIEmbeddingClient::class),
            new DisabledVectorStore
        );
    }

    public function test_chunk_text_respects_max_tokens_boundary(): void
    {
        $text = str_repeat('word ', 200);
        $chunks = $this->service->chunkText($text);
        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(800, $chunk['tokens']);
            $this->assertArrayHasKey('content_hash', $chunk);
        }
    }

    public function test_chunk_text_produces_multiple_chunks_when_config_small(): void
    {
        config(['ai_assistant.v2.chunking' => [
            'chunk_tokens_min' => 10,
            'chunk_tokens_max' => 50,
            'chunk_overlap_tokens' => 5,
        ]]);
        $service = new AiIndexingService(
            $this->createMock(OpenAIEmbeddingClient::class),
            new \App\Services\AI\VectorStore\DisabledVectorStore
        );
        $text = str_repeat('word ', 80);
        $chunks = $service->chunkText($text);
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertArrayHasKey('content', $chunk);
            $this->assertArrayHasKey('content_hash', $chunk);
        }
    }

    public function test_approximate_tokens(): void
    {
        $this->assertEquals(25, $this->service->approximateTokens(str_repeat('x', 100)));
    }
}
