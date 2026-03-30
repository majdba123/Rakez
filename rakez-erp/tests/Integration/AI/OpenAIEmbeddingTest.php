<?php

namespace Tests\Integration\AI;

use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\Rag\EmbeddingService;
use Tests\TestCase;
use Tests\Traits\ReadsDotEnvForTest;

/**
 * Integration tests that call the real OpenAI API.
 * Requires OPENAI_API_KEY and AI_REAL_TESTS=true in .env.
 *
 * @group integration
 * @group ai-e2e-real
 */
class OpenAIEmbeddingTest extends TestCase
{
    use ReadsDotEnvForTest;

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

    public function test_real_embedding_generation(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);

        $embedding = $service->embed('This is a test text about real estate in Saudi Arabia.');

        $this->assertIsArray($embedding);
        $this->assertCount(1536, $embedding);
        $this->assertIsFloat($embedding[0]);
    }

    public function test_real_embedding_batch(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);

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
        $service = new EmbeddingService(new AiOpenAiGateway);

        $embedding = $service->embed('نظام إدارة العقارات في المملكة العربية السعودية');

        $this->assertCount(1536, $embedding);
    }

    public function test_similar_texts_have_high_similarity(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);

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
