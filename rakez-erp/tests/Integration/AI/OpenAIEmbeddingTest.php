<?php

namespace Tests\Integration\AI;

use App\Services\AI\Rag\EmbeddingService;
use Tests\TestCase;

/**
 * Integration tests that call the real OpenAI API.
 * Requires OPENAI_API_KEY to be set in .env.
 *
 * @group integration
 */
class OpenAIEmbeddingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Read real key from .env (phpunit.xml overrides with fake key)
        $realKey = $this->getRealApiKey();

        if (! $realKey || str_starts_with($realKey, 'test-fake')) {
            $this->markTestSkipped('Real OPENAI_API_KEY not available — skipping integration test.');
        }

        // Override the config with real key for integration tests
        config(['openai.api_key' => $realKey]);
    }

    private function getRealApiKey(): ?string
    {
        $envFile = base_path('.env');
        if (! file_exists($envFile)) {
            return null;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }
            if (str_starts_with($line, 'OPENAI_API_KEY=')) {
                return trim(substr($line, strlen('OPENAI_API_KEY=')));
            }
        }

        return null;
    }

    public function test_real_embedding_generation(): void
    {
        $service = new EmbeddingService;

        $embedding = $service->embed('This is a test text about real estate in Saudi Arabia.');

        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertIsFloat($embedding[0]);
    }

    public function test_real_embedding_batch(): void
    {
        $service = new EmbeddingService;

        $embeddings = $service->embedBatch([
            'First text about sales',
            'Second text about marketing',
        ]);

        $this->assertCount(2, $embeddings);
        $this->assertCount(1536, $embeddings[0]);
        $this->assertCount(1536, $embeddings[1]);
    }

    public function test_arabic_text_embedding(): void
    {
        $service = new EmbeddingService;

        $embedding = $service->embed('نظام إدارة العقارات في المملكة العربية السعودية');

        $this->assertCount(1536, $embedding);
    }

    public function test_similar_texts_have_high_similarity(): void
    {
        $service = new EmbeddingService;

        $emb1 = $service->embed('How to buy a house in Riyadh');
        $emb2 = $service->embed('Guide to purchasing real estate in Riyadh');
        $emb3 = $service->embed('Best pizza recipes in Italy');

        $sim12 = \App\Services\AI\Rag\VectorSearchService::cosineSimilarity($emb1, $emb2);
        $sim13 = \App\Services\AI\Rag\VectorSearchService::cosineSimilarity($emb1, $emb3);

        // Similar texts should have higher similarity
        $this->assertGreaterThan($sim13, $sim12, 'Related texts should be more similar than unrelated ones');
        $this->assertGreaterThan(0.7, $sim12, 'Related texts should have similarity > 0.7');
    }
}
