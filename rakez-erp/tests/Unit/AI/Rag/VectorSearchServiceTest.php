<?php

namespace Tests\Unit\AI\Rag;

use App\Services\AI\Rag\VectorSearchService;
use PHPUnit\Framework\TestCase;

class VectorSearchServiceTest extends TestCase
{
    public function test_cosine_similarity_identical_vectors(): void
    {
        $v = [1.0, 2.0, 3.0];
        $similarity = VectorSearchService::cosineSimilarity($v, $v);

        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_orthogonal_vectors(): void
    {
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];
        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_opposite_vectors(): void
    {
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];
        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_similar_vectors(): void
    {
        $a = [1.0, 2.0, 3.0];
        $b = [1.1, 2.1, 3.1];
        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        $this->assertGreaterThan(0.99, $similarity);
    }

    public function test_cosine_similarity_empty_vectors(): void
    {
        $this->assertEquals(0.0, VectorSearchService::cosineSimilarity([], []));
    }

    public function test_cosine_similarity_zero_vector(): void
    {
        $a = [0.0, 0.0, 0.0];
        $b = [1.0, 2.0, 3.0];
        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        $this->assertEquals(0.0, $similarity);
    }

    public function test_cosine_similarity_different_magnitudes(): void
    {
        $a = [1.0, 0.0];
        $b = [100.0, 0.0];
        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        // Same direction, different magnitudes → similarity = 1.0
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function test_cosine_similarity_high_dimensional(): void
    {
        $dims = 1536;
        $a = array_fill(0, $dims, 0.5);
        $b = array_fill(0, $dims, 0.5);
        $b[0] = 0.6; // Slightly different

        $similarity = VectorSearchService::cosineSimilarity($a, $b);

        $this->assertGreaterThan(0.99, $similarity);
        $this->assertLessThan(1.0, $similarity);
    }
}
