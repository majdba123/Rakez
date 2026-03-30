<?php

namespace Tests\Unit\AI\Rag;

use App\Services\AI\AiOpenAiGateway;
use App\Services\AI\Rag\EmbeddingService;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Embeddings\CreateResponse;
use Tests\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_embed_returns_vector(): void
    {
        $fakeEmbedding = array_fill(0, 1536, 0.1);

        OpenAI::fake([
            CreateResponse::fake([
                'data' => [
                    ['object' => 'embedding', 'embedding' => $fakeEmbedding, 'index' => 0],
                ],
            ]),
        ]);

        $service = new EmbeddingService(new AiOpenAiGateway);
        $result = $service->embed('Test text');

        $this->assertCount(1536, $result);
        $this->assertEquals(0.1, $result[0]);
    }

    public function test_embed_empty_string_returns_zero_vector(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);
        $result = $service->embed('');

        $this->assertCount(1536, $result);
        $this->assertEquals(0.0, $result[0]);
    }

    public function test_embed_batch_returns_multiple_vectors(): void
    {
        $fakeEmbedding1 = array_fill(0, 1536, 0.1);
        $fakeEmbedding2 = array_fill(0, 1536, 0.2);

        OpenAI::fake([
            CreateResponse::fake([
                'data' => [
                    ['object' => 'embedding', 'embedding' => $fakeEmbedding1, 'index' => 0],
                    ['object' => 'embedding', 'embedding' => $fakeEmbedding2, 'index' => 1],
                ],
            ]),
        ]);

        $service = new EmbeddingService(new AiOpenAiGateway);
        $results = $service->embedBatch(['Text 1', 'Text 2']);

        $this->assertCount(2, $results);
        $this->assertCount(1536, $results[0]);
        $this->assertCount(1536, $results[1]);
    }

    public function test_embed_batch_empty_array(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);
        $results = $service->embedBatch([]);

        $this->assertEmpty($results);
    }

    public function test_dimensions_returns_configured_value(): void
    {
        $service = new EmbeddingService(new AiOpenAiGateway);
        $this->assertEquals(1536, $service->dimensions());
    }
}
